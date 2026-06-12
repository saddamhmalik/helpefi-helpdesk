<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Repositories\UsageRepository;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private UsageRepository $usage,
        private CentralSettingsService $centralSettings,
        private RazorpayBillingService $razorpay,
    ) {
    }

    public function snapshot(): array
    {
        $subscription = $this->subscriptions->current();
        $plan = $this->currentPlan($subscription);
        $usage = $this->usageStats();
        $features = $this->effectiveFeatures($subscription, $plan);

        return [
            'plan' => $this->displayPlan($subscription, $plan),
            'currency' => $this->centralSettings->currencyMeta(),
            'status' => $subscription->status,
            'on_trial' => $subscription->isOnTrial(),
            'trial_expired' => $subscription->isTrialExpired(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'trial_days_remaining' => $subscription->trialDaysRemaining(),
            'renews_at' => $subscription->renews_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'access_ends_at' => $subscription->access_ends_at?->toIso8601String(),
            'in_grace_period' => $subscription->isInGracePeriod(),
            'grace_days_remaining' => $subscription->graceDaysRemaining(),
            'cancellation_grace_days' => app(SubscriptionLifecycleService::class)->graceDays(),
            'cancellation_pending' => $subscription->cancelled_at !== null && $subscription->isActive(),
            'show_cancellation_banner' => $subscription->cancelled_at !== null
                && $subscription->access_ends_at?->isFuture(),
            'features' => $features,
            'active_addons' => $subscription->active_addons ?? [],
            'available_addons' => $this->availableAddons($subscription),
            'limits' => $this->formattedLimits($plan['limits']),
            'usage' => $usage,
            'available_plans' => collect($this->plans->all())
                ->map(fn (array $item, string $slug) => [
                    'slug' => $slug,
                    'name' => $item['name'],
                    'price' => $item['price_monthly'] ?? $item['price'],
                    'price_monthly' => $item['price_monthly'] ?? $item['price'],
                    'price_yearly' => $item['price_yearly'] ?? PlanCatalogDefinition::defaultYearlyPrice((int) ($item['price_monthly'] ?? $item['price'] ?? 0)),
                    'limits' => $this->formattedLimits($item['limits']),
                    'features' => $item['features'],
                    'billing_ready' => ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'month'))
                        || ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'year')),
                    'billing_ready_monthly' => ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'month')),
                    'billing_ready_yearly' => ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'year')),
                ])
                ->values()
                ->all(),
            'razorpay_enabled' => $this->razorpay->isEnabled(),
            'has_razorpay_subscription' => (bool) $subscription->razorpay_subscription_id,
            'trial_offer' => $this->trialOffer(),
        ];
    }

    private function trialOffer(): array
    {
        $trialPlan = $this->plans->find(config('billing.trial_plan', 'enterprise'));

        return [
            'days' => $this->centralSettings->trialDays(),
            'plan_name' => $trialPlan['name'],
            'plan_slug' => config('billing.trial_plan', 'enterprise'),
            'limits' => $this->formattedLimits($trialPlan['limits']),
            'features' => $trialPlan['features'],
        ];
    }

    public function canUseFeature(string $feature): bool
    {
        if (! $this->subscriptions->current()->isAccessible()) {
            return false;
        }

        $subscription = $this->subscriptions->current();
        $plan = $this->currentPlan($subscription);

        if (in_array($feature, $plan['features'], true)) {
            return true;
        }

        return $this->hasActiveAddonFeature($subscription, $feature);
    }

    public function hasAddon(string $addonKey): bool
    {
        $subscription = $this->subscriptions->current();

        return in_array($addonKey, $subscription->active_addons ?? [], true);
    }

    public function activateAddon(string $addonKey): Subscription
    {
        $addon = $this->centralSettings->findAddon($addonKey);

        if (! ($addon['enabled'] ?? true)) {
            throw ValidationException::withMessages([
                'addon' => 'This add-on is not available.',
            ]);
        }

        $subscription = $this->subscriptions->current();

        if (! $subscription->isAccessible()) {
            throw ValidationException::withMessages([
                'addon' => 'Your workspace does not have an active subscription.',
            ]);
        }

        if (! $subscription->isOnTrial() && ! $subscription->isActive()) {
            throw ValidationException::withMessages([
                'addon' => 'Activate a subscription plan before purchasing add-ons.',
            ]);
        }

        $active = $subscription->active_addons ?? [];

        if (in_array($addonKey, $active, true)) {
            return $subscription;
        }

        return $this->subscriptions->update($subscription, [
            'active_addons' => array_values(array_unique([...$active, $addonKey])),
        ]);
    }

    public function deactivateAddon(string $addonKey): Subscription
    {
        $subscription = $this->subscriptions->current();
        $active = array_values(array_filter(
            $subscription->active_addons ?? [],
            fn (string $key) => $key !== $addonKey,
        ));

        $razorpayAddonItems = $subscription->razorpay_addon_items ?? [];
        unset($razorpayAddonItems[$addonKey]);

        return $this->subscriptions->update($subscription, [
            'active_addons' => $active,
            'razorpay_addon_items' => $razorpayAddonItems,
        ]);
    }

    public function purchaseAddon(string $addonKey, string $customerEmail, string $customerName = ''): Subscription|array
    {
        $this->centralSettings->findAddon($addonKey);

        $subscription = $this->subscriptions->current();

        if ($subscription->isOnTrial()) {
            return $this->activateAddon($addonKey);
        }

        if ($this->razorpay->isEnabled()) {
            return $this->razorpay->purchaseAddon($addonKey, $customerEmail, $customerName);
        }

        return $this->activateAddon($addonKey);
    }

    public function cancelAddon(string $addonKey): Subscription
    {
        if ($this->razorpay->isEnabled()) {
            return $this->razorpay->cancelAddon($addonKey);
        }

        return $this->deactivateAddon($addonKey);
    }

    public function withinLimit(string $key, int $buffer = 0): bool
    {
        if (! $this->subscriptions->current()->isAccessible()) {
            return false;
        }

        $limit = $this->currentPlan()['limits'][$key] ?? null;

        if ($limit === null) {
            return true;
        }

        return $this->currentUsage($key) + $buffer <= $limit;
    }

    public function assertFeature(string $feature): void
    {
        if ($this->canUseFeature($feature)) {
            return;
        }

        $plan = $this->currentPlan();

        throw new AuthorizationException(
            "The {$plan['name']} plan does not include {$feature}. Upgrade to unlock this feature.",
        );
    }

    public function assertLimit(string $key, int $buffer = 0): void
    {
        if ($this->withinLimit($key, $buffer)) {
            return;
        }

        $limit = $this->currentPlan()['limits'][$key];
        $label = $key === 'agents' ? 'team members' : 'monthly tickets';

        throw ValidationException::withMessages([
            $key === 'agents' ? 'email' : 'subject' => "Plan limit reached: maximum {$limit} {$label} on the {$this->currentPlan()['name']} plan.",
        ]);
    }

    public function usesRazorpayCheckout(): bool
    {
        return $this->razorpay->isEnabled();
    }

    public function initiatePlanChange(
        string $slug,
        string $customerEmail,
        string $customerName,
        string $successUrl,
        string $interval = 'month',
    ): array|string|Subscription {
        $this->plans->find($slug);

        if ($this->razorpay->isEnabled()) {
            return $this->razorpay->prepareCheckoutSession(
                $slug,
                $customerEmail,
                $customerName,
                $successUrl,
                $interval,
            );
        }

        return $this->changePlan($slug, $interval);
    }

    public function verifyRazorpayCheckout(
        string $paymentId,
        string $subscriptionId,
        string $signature,
    ): Subscription {
        return $this->razorpay->verifySubscriptionPayment($paymentId, $subscriptionId, $signature);
    }

    public function cancelSubscription(): Subscription
    {
        return $this->razorpay->cancelSubscription();
    }

    public function changePlan(string $slug, string $interval = 'month'): Subscription
    {
        $this->plans->find($slug);
        $subscription = $this->subscriptions->current();

        if ($subscription->isOnTrial()) {
            throw ValidationException::withMessages([
                'plan' => 'Your free trial is still active. Choose a plan after the trial ends.',
            ]);
        }

        if (! $subscription->isTrialExpired() && ! $subscription->isActive()) {
            throw ValidationException::withMessages([
                'plan' => 'Activate a subscription plan before changing plans.',
            ]);
        }

        return $this->subscriptions->update($subscription, [
            'plan' => $slug,
            'billing_interval' => $interval,
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'renews_at' => $interval === 'year' ? now()->addYear() : now()->addMonth(),
        ]);
    }

    private function currentPlan(?Subscription $subscription = null): array
    {
        $subscription ??= $this->subscriptions->current();

        if ($subscription->plan) {
            return $this->plans->find($subscription->plan);
        }

        return $this->plans->find(config('billing.trial_plan', 'enterprise'));
    }

    private function displayPlan(Subscription $subscription, array $plan): array
    {
        if ($subscription->isOnTrial()) {
            return [
                'slug' => null,
                'name' => 'Free trial',
                'price' => 0,
            ];
        }

        if ($subscription->plan) {
            $interval = $subscription->billing_interval ?? 'month';

            return [
                'slug' => $subscription->plan,
                'name' => $plan['name'],
                'price' => PlanCatalogDefinition::priceForInterval($plan, $interval),
                'price_monthly' => PlanCatalogDefinition::priceForInterval($plan, 'month'),
                'price_yearly' => PlanCatalogDefinition::priceForInterval($plan, 'year'),
                'billing_interval' => $interval,
            ];
        }

        return [
            'slug' => null,
            'name' => 'No plan selected',
            'price' => 0,
        ];
    }

    private function usageStats(): array
    {
        return [
            'agents' => $this->usage->agentCount(),
            'pending_invites' => $this->usage->pendingInviteCount(),
            'tickets_monthly' => $this->usage->ticketsCreatedThisMonth(),
        ];
    }

    private function currentUsage(string $key): int
    {
        return match ($key) {
            'agents' => $this->usage->agentCount() + $this->usage->pendingInviteCount(),
            'tickets_monthly' => $this->usage->ticketsCreatedThisMonth(),
            default => 0,
        };
    }

    private function formattedLimits(array $limits): array
    {
        return collect($limits)
            ->map(fn ($value) => $value === null ? 'unlimited' : $value)
            ->all();
    }

    private function effectiveFeatures(Subscription $subscription, array $plan): array
    {
        $features = $plan['features'] ?? [];

        foreach ($subscription->active_addons ?? [] as $addonKey) {
            $feature = AddonCatalogDefinition::featureForAddon($addonKey);

            if ($feature) {
                $features[] = $feature;
            }
        }

        return array_values(array_unique($features));
    }

    private function hasActiveAddonFeature(Subscription $subscription, string $feature): bool
    {
        foreach ($subscription->active_addons ?? [] as $addonKey) {
            if (AddonCatalogDefinition::featureForAddon($addonKey) !== $feature) {
                continue;
            }

            if ($subscription->isOnTrial()) {
                return true;
            }

            if ($this->razorpay->isEnabled()) {
                return isset(($subscription->razorpay_addon_items ?? [])[$addonKey]);
            }

            return $subscription->isActive();
        }

        return false;
    }

    private function availableAddons(Subscription $subscription): array
    {
        $currency = $this->centralSettings->currencyMeta();

        return collect($this->centralSettings->addonCatalog())
            ->filter(fn (array $addon) => $addon['enabled'] ?? true)
            ->map(fn (array $addon, string $key) => [
                'key' => $key,
                'name' => $addon['name'],
                'feature' => $addon['feature'],
                'description' => $addon['description'],
                'price_monthly' => $addon['price_monthly'],
                'currency' => $currency,
                'active' => in_array($key, $subscription->active_addons ?? [], true),
                'trial_access' => $subscription->isOnTrial()
                    && in_array($key, $subscription->active_addons ?? [], true),
                'paid' => isset(($subscription->razorpay_addon_items ?? [])[$key]),
                'billing_ready' => ! empty(AddonCatalogDefinition::razorpayPlanId($addon)),
            ])
            ->values()
            ->all();
    }
}
