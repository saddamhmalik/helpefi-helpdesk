<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class SubscriptionLifecycleService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private CentralSettingsService $centralSettings,
    ) {
    }

    public function graceDays(): int
    {
        return max(1, (int) config('billing.cancellation_grace_days', 3));
    }

    public function applyRazorpaySubscription(Subscription $subscription, object $razorpaySubscription): Subscription
    {
        $status = (string) ($razorpaySubscription->status ?? '');
        $cancelAtCycleEnd = (bool) ($razorpaySubscription->cancel_at_cycle_end ?? false);
        $periodEnd = isset($razorpaySubscription->current_end)
            ? Carbon::createFromTimestamp($razorpaySubscription->current_end)
            : null;
        $planId = $razorpaySubscription->plan_id ?? $subscription->razorpay_plan_id;
        $notes = (array) ($razorpaySubscription->notes ?? []);
        $interval = $notes['billing_interval']
            ?? $subscription->billing_interval
            ?? 'month';
        $plan = $notes['plan']
            ?? $this->resolvePlanFromPlanId($planId)
            ?? $subscription->plan;

        [$activeAddons, $razorpayAddonItems] = $this->resolveAddonsFromRazorpaySubscription($razorpaySubscription);

        if (in_array($status, ['active', 'authenticated'], true) && ! $cancelAtCycleEnd) {
            return $this->restorePaidAccess($subscription, [
                'plan' => $plan,
                'billing_interval' => $interval,
                'status' => Subscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'renews_at' => $periodEnd,
                'razorpay_subscription_id' => $razorpaySubscription->id,
                'razorpay_plan_id' => $planId,
                'cancelled_at' => null,
                'access_ends_at' => null,
                'active_addons' => $activeAddons,
                'razorpay_addon_items' => $razorpayAddonItems,
            ]);
        }

        if (in_array($status, ['active', 'authenticated'], true) && $cancelAtCycleEnd) {
            return $this->subscriptions->update($subscription, [
                'plan' => $plan,
                'billing_interval' => $interval,
                'status' => Subscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'renews_at' => $periodEnd,
                'razorpay_subscription_id' => $razorpaySubscription->id,
                'razorpay_plan_id' => $planId,
                'cancelled_at' => isset($razorpaySubscription->ended_at)
                    ? Carbon::createFromTimestamp($razorpaySubscription->ended_at)
                    : now(),
                'access_ends_at' => $this->graceEndsAt($periodEnd),
            ]);
        }

        if (in_array($status, ['pending', 'halted'], true)) {
            return $this->subscriptions->update($subscription, [
                'plan' => $plan,
                'billing_interval' => $interval,
                'status' => Subscription::STATUS_PAST_DUE,
                'trial_ends_at' => null,
                'renews_at' => $periodEnd,
                'razorpay_subscription_id' => $razorpaySubscription->id,
                'razorpay_plan_id' => $planId,
                'access_ends_at' => $this->graceEndsAt(now()),
            ]);
        }

        return $this->markCancelled($subscription, [
            'plan' => $plan,
            'billing_interval' => $interval,
            'razorpay_subscription_id' => $razorpaySubscription->id,
            'razorpay_plan_id' => $planId,
            'renews_at' => null,
            'cancelled_at' => isset($razorpaySubscription->ended_at)
                ? Carbon::createFromTimestamp($razorpaySubscription->ended_at)
                : now(),
        ], $periodEnd);
    }

    public function markCancelled(Subscription $subscription, array $data = [], ?Carbon $periodEnd = null): Subscription
    {
        $graceEndsAt = $this->graceEndsAt($periodEnd);

        if ($subscription->access_ends_at?->greaterThan($graceEndsAt)) {
            $graceEndsAt = $subscription->access_ends_at;
        }

        return $this->subscriptions->update($subscription, array_merge($data, [
            'status' => Subscription::STATUS_CANCELLED,
            'trial_ends_at' => null,
            'access_ends_at' => $graceEndsAt,
        ]));
    }

    public function restorePaidAccess(Subscription $subscription, array $data): Subscription
    {
        Tenant::query()
            ->where('id', $subscription->tenant_id)
            ->update(['is_blocked' => false]);

        return $this->subscriptions->update($subscription, $data);
    }

    public function enforceExpiredGrace(): int
    {
        $subscriptions = Subscription::query()
            ->whereNotNull('access_ends_at')
            ->where('access_ends_at', '<', now())
            ->whereIn('status', [Subscription::STATUS_CANCELLED, Subscription::STATUS_PAST_DUE])
            ->get();

        $blocked = 0;

        foreach ($subscriptions as $subscription) {
            $updated = Tenant::query()
                ->where('id', $subscription->tenant_id)
                ->where('is_blocked', false)
                ->update(['is_blocked' => true]);

            $blocked += $updated;
        }

        return $blocked;
    }

    private function graceEndsAt(?Carbon $periodEnd): Carbon
    {
        $graceStart = $periodEnd && $periodEnd->isFuture() ? $periodEnd : now();

        return $graceStart->copy()->addDays($this->graceDays());
    }

    private function resolvePlanFromPlanId(?string $planId): ?string
    {
        if (! $planId) {
            return null;
        }

        foreach ($this->plans->all() as $slug => $plan) {
            foreach (['razorpay_plan_id_monthly', 'razorpay_plan_id_yearly', 'razorpay_plan_id'] as $key) {
                if (($plan[$key] ?? null) === $planId) {
                    return $slug;
                }
            }
        }

        return null;
    }

    private function resolveAddonsFromRazorpaySubscription(object $razorpaySubscription): array
    {
        $activeAddons = [];
        $razorpayAddonItems = [];
        $addonCatalog = $this->centralSettings->addonCatalog();
        $planToAddon = [];

        foreach ($addonCatalog as $key => $addon) {
            foreach (AddonCatalogDefinition::razorpayPlanIds($addon) as $planId) {
                $planToAddon[$planId] = $key;
            }
        }

        foreach ($razorpaySubscription->addons ?? [] as $item) {
            $itemPlanId = (string) data_get($item, 'item.id', '');
            $addonKey = $planToAddon[$itemPlanId] ?? data_get($item, 'notes.addon_key');

            if (! is_string($addonKey) || $addonKey === '') {
                continue;
            }

            $activeAddons[] = $addonKey;
            $razorpayAddonItems[$addonKey] = (string) data_get($item, 'id', '');
        }

        return [array_values(array_unique($activeAddons)), $razorpayAddonItems];
    }
}
