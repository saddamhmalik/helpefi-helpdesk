<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Repositories\UsageRepository;
use App\Domains\Billing\Support\RegionCurrencyResolver;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Support\TenantCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private UsageRepository $usage,
        private CentralSettingsService $centralSettings,
        private RazorpayBillingService $razorpay,
        private RegionCurrencyResolver $regionCurrency,
    ) {
    }

    public function snapshot(): array
    {
        $subscription = $this->subscriptions->current();
        $plan = $this->currentPlan($subscription);
        $usage = $this->usageStats();
        $features = $this->effectiveFeatures($subscription, $plan);

        $displayCurrency = $subscription->currency ?: $this->regionCurrency->resolve(request());
        $india = $this->regionCurrency->isIndiaCurrency($displayCurrency);

        return [
            'plan' => $this->displayPlan($subscription, $plan, $india),
            'currency' => CurrencyCatalog::meta($displayCurrency),
            'base_currency' => $this->centralSettings->currencyMeta(),
            'currency_locked' => $subscription->isActive() && $subscription->currency !== null,
            'india_pricing' => $this->centralSettings->indiaPricingEnabled(),
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
                    'custom_pricing' => $item['custom_pricing'] ?? false,
                    'price' => PlanCatalogDefinition::priceForInterval($item, 'month', $india),
                    'price_monthly' => PlanCatalogDefinition::priceForInterval($item, 'month', $india),
                    'price_yearly' => PlanCatalogDefinition::priceForInterval($item, 'year', $india),
                    'limits' => $this->formattedLimits($item['limits']),
                    'features' => $item['features'],
                    'billing_ready' => ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'month', $india))
                        || ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'year', $india)),
                    'billing_ready_monthly' => ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'month', $india)),
                    'billing_ready_yearly' => ! empty(PlanCatalogDefinition::razorpayPlanIdForInterval($item, 'year', $india)),
                ])
                ->values()
                ->all(),
            'razorpay_enabled' => $this->razorpay->isEnabled(),
            'has_razorpay_subscription' => (bool) $subscription->razorpay_subscription_id,
            'trial_offer' => $this->trialOffer(),
        ];
    }

    public function layoutSnapshot(): array
    {
        if (! tenant('id') || app()->environment('testing')) {
            return $this->buildLayoutSnapshot();
        }

        return Cache::remember(
            TenantCache::key('billing.layout'),
            60,
            fn () => $this->buildLayoutSnapshot(),
        );
    }

    private function buildLayoutSnapshot(): array
    {
        $subscription = $this->subscriptions->current();
        $plan = $this->currentPlan($subscription);
        $displayCurrency = $subscription->currency ?: $this->regionCurrency->resolve(request());
        $india = $this->regionCurrency->isIndiaCurrency($displayCurrency);
        $currency = CurrencyCatalog::meta($displayCurrency);

        return [
            'currency' => $currency,
            'base_currency' => $this->centralSettings->currencyMeta(),
            'india_pricing' => $this->centralSettings->indiaPricingEnabled(),
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
            'features' => $this->effectiveFeatures($subscription, $plan),
            'limits' => $this->formattedLimits($plan['limits']),
            'usage' => $this->usageStats(),
            'available_plans' => collect($this->plans->all())
                ->map(fn (array $item, string $slug) => [
                    'slug' => $slug,
                    'name' => $item['name'],
                    'features' => $item['features'],
                ])
                ->values()
                ->all(),
            'available_addons' => collect($this->centralSettings->addonCatalog())
                ->filter(fn (array $addon) => $addon['enabled'] ?? true)
                ->map(fn (array $addon, string $key) => [
                    'key' => $key,
                    'name' => $addon['name'],
                    'feature' => $addon['feature'],
                    'price_monthly' => AddonCatalogDefinition::priceForRegion($addon, $india),
                    'currency' => $currency,
                ])
                ->values()
                ->all(),
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

        $feature = AddonCatalogDefinition::featureForAddon($addonKey);

        if ($feature && in_array($feature, $this->currentPlan($subscription)['features'] ?? [], true)) {
            throw ValidationException::withMessages([
                'addon' => 'This feature is already included in your current plan.',
            ]);
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
        $feature = AddonCatalogDefinition::featureForAddon($addonKey);

        if ($feature && in_array($feature, $this->currentPlan($subscription)['features'] ?? [], true)) {
            throw ValidationException::withMessages([
                'addon' => 'This feature is already included in your current plan.',
            ]);
        }

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
        string $successRedirect,
        string $interval = 'month',
        string $cancelRedirect = '/settings/billing?checkout=cancelled&section=plans',
        ?string $currency = null,
    ): array|string|Subscription {
        $this->assertSelfServiceable($slug);

        if ($this->razorpay->isEnabled()) {
            return $this->razorpay->prepareCheckoutSession(
                $slug,
                $customerEmail,
                $customerName,
                $successRedirect,
                $interval,
                $cancelRedirect,
                $currency ?? $this->regionCurrency->resolve(request()),
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
        if ($this->razorpay->isEnabled()) {
            return $this->razorpay->cancelSubscription();
        }

        $subscription = $this->subscriptions->current();

        return app(SubscriptionLifecycleService::class)->markCancelled($subscription, [
            'razorpay_subscription_id' => null,
            'razorpay_plan_id' => null,
            'renews_at' => null,
            'cancelled_at' => now(),
        ], $subscription->renews_at);
    }

    public function changePlan(string $slug, string $interval = 'month'): Subscription
    {
        $this->assertSelfServiceable($slug);
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

    private function assertSelfServiceable(string $slug): array
    {
        $plan = $this->plans->find($slug);

        if (! empty($plan['custom_pricing'])) {
            throw ValidationException::withMessages([
                'plan' => 'This plan has custom pricing. Please contact us to get started.',
            ]);
        }

        return $plan;
    }

    private function currentPlan(?Subscription $subscription = null): array
    {
        $subscription ??= $this->subscriptions->current();

        if ($subscription->plan) {
            return $this->plans->find($subscription->plan);
        }

        return $this->plans->find(config('billing.trial_plan', 'enterprise'));
    }

    private function displayPlan(Subscription $subscription, array $plan, bool $india = false): array
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
            $hasCustomAmount = $subscription->custom_amount !== null;
            $planIsCustom = ! empty($plan['custom_pricing']);
            $catalogPrice = PlanCatalogDefinition::priceForInterval($plan, $interval, $india);

            return [
                'slug' => $subscription->plan,
                'name' => $plan['name'],
                'price' => $hasCustomAmount ? $subscription->custom_amount : ($planIsCustom ? null : $catalogPrice),
                'price_monthly' => PlanCatalogDefinition::priceForInterval($plan, 'month', $india),
                'price_yearly' => PlanCatalogDefinition::priceForInterval($plan, 'year', $india),
                'billing_interval' => $interval,
                'is_custom_price' => $hasCustomAmount || $planIsCustom,
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
        $displayCurrency = $subscription->currency ?: $this->regionCurrency->resolve(request());
        $india = $this->regionCurrency->isIndiaCurrency($displayCurrency);
        $currency = CurrencyCatalog::meta($displayCurrency);
        $planFeatures = $this->currentPlan($subscription)['features'] ?? [];

        return collect($this->centralSettings->addonCatalog())
            ->filter(fn (array $addon) => $addon['enabled'] ?? true)
            ->map(function (array $addon, string $key) use ($subscription, $currency, $planFeatures, $india) {
                $includedInPlan = in_array($addon['feature'], $planFeatures, true);

                return [
                    'key' => $key,
                    'name' => $addon['name'],
                    'feature' => $addon['feature'],
                    'description' => $addon['description'],
                    'price_monthly' => AddonCatalogDefinition::priceForRegion($addon, $india),
                    'currency' => $currency,
                    'included_in_plan' => $includedInPlan,
                    'active' => $includedInPlan || in_array($key, $subscription->active_addons ?? [], true),
                    'trial_access' => $subscription->isOnTrial()
                        && in_array($key, $subscription->active_addons ?? [], true),
                    'paid' => isset(($subscription->razorpay_addon_items ?? [])[$key]),
                    'billing_ready' => ! empty(AddonCatalogDefinition::razorpayPlanIdForRegion($addon, $india)),
                ];
            })
            ->values()
            ->all();
    }
}
