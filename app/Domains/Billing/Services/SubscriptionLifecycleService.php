<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class SubscriptionLifecycleService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
    ) {
    }

    public function graceDays(): int
    {
        return max(1, (int) config('billing.cancellation_grace_days', 3));
    }

    public function applyStripeSubscription(Subscription $subscription, object $stripeSubscription): Subscription
    {
        $status = (string) ($stripeSubscription->status ?? '');
        $cancelAtPeriodEnd = (bool) ($stripeSubscription->cancel_at_period_end ?? false);
        $periodEnd = isset($stripeSubscription->current_period_end)
            ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)
            : null;
        $priceId = $stripeSubscription->items->data[0]->price->id ?? $subscription->stripe_price_id;
        $plan = $stripeSubscription->metadata->plan
            ?? $this->resolvePlanFromPriceId($priceId)
            ?? $subscription->plan;

        if (in_array($status, ['active', 'trialing'], true) && ! $cancelAtPeriodEnd) {
            return $this->restorePaidAccess($subscription, [
                'plan' => $plan,
                'status' => Subscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'renews_at' => $periodEnd,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_price_id' => $priceId,
                'cancelled_at' => null,
                'access_ends_at' => null,
            ]);
        }

        if (in_array($status, ['active', 'trialing'], true) && $cancelAtPeriodEnd) {
            return $this->subscriptions->update($subscription, [
                'plan' => $plan,
                'status' => Subscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'renews_at' => $periodEnd,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_price_id' => $priceId,
                'cancelled_at' => isset($stripeSubscription->canceled_at)
                    ? Carbon::createFromTimestamp($stripeSubscription->canceled_at)
                    : now(),
                'access_ends_at' => $this->graceEndsAt($periodEnd),
            ]);
        }

        if (in_array($status, ['past_due', 'unpaid'], true)) {
            return $this->subscriptions->update($subscription, [
                'plan' => $plan,
                'status' => Subscription::STATUS_PAST_DUE,
                'trial_ends_at' => null,
                'renews_at' => $periodEnd,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_price_id' => $priceId,
            ]);
        }

        return $this->markCancelled($subscription, [
            'plan' => $plan,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_price_id' => $priceId,
            'renews_at' => null,
            'cancelled_at' => isset($stripeSubscription->canceled_at)
                ? Carbon::createFromTimestamp($stripeSubscription->canceled_at)
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

    private function resolvePlanFromPriceId(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        foreach ($this->plans->all() as $slug => $plan) {
            if (($plan['stripe_price_id'] ?? null) === $priceId) {
                return $slug;
            }
        }

        return null;
    }
}
