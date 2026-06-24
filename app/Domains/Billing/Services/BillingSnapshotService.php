<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Support\RegionCurrencyResolver;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class BillingSnapshotService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private PlanEntitlementService $entitlements,
        private CentralSettingsService $centralSettings,
        private RazorpayBillingService $razorpay,
        private RegionCurrencyResolver $regionCurrency,
        private SubscriptionLifecycleService $lifecycle,
    ) {
    }

    public function snapshot(): array
    {
        $subscription = $this->subscriptions->current();
        $plan = $this->entitlements->currentPlan($subscription);
        $usage = $this->entitlements->usageStats();
        $features = $this->entitlements->effectiveFeatures($subscription, $plan);

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
            'cancellation_grace_days' => $this->lifecycle->graceDays(),
            'cancellation_pending' => $subscription->cancelled_at !== null && $subscription->isActive(),
            'show_cancellation_banner' => $subscription->cancelled_at !== null
                && $subscription->access_ends_at?->isFuture(),
            'features' => $features,
            'active_addons' => $subscription->active_addons ?? [],
            'available_addons' => $this->availableAddons($subscription),
            'limits' => $this->entitlements->formattedLimits($plan['limits']),
            'usage' => $usage,
            'available_plans' => collect($this->plans->all())
                ->map(fn (array $item, string $slug) => [
                    'slug' => $slug,
                    'name' => $item['name'],
                    'custom_pricing' => $item['custom_pricing'] ?? false,
                    'price' => PlanCatalogDefinition::priceForInterval($item, 'month', $india),
                    'price_monthly' => PlanCatalogDefinition::priceForInterval($item, 'month', $india),
                    'price_yearly' => PlanCatalogDefinition::priceForInterval($item, 'year', $india),
                    'limits' => $this->entitlements->formattedLimits($item['limits']),
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
        $plan = $this->entitlements->currentPlan($subscription);
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
            'cancellation_grace_days' => $this->lifecycle->graceDays(),
            'cancellation_pending' => $subscription->cancelled_at !== null && $subscription->isActive(),
            'show_cancellation_banner' => $subscription->cancelled_at !== null
                && $subscription->access_ends_at?->isFuture(),
            'features' => $this->entitlements->effectiveFeatures($subscription, $plan),
            'limits' => $this->entitlements->formattedLimits($plan['limits']),
            'usage' => $this->entitlements->usageStats(),
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
            'limits' => $this->entitlements->formattedLimits($trialPlan['limits']),
            'features' => $trialPlan['features'],
        ];
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

    private function availableAddons(Subscription $subscription): array
    {
        $displayCurrency = $subscription->currency ?: $this->regionCurrency->resolve(request());
        $india = $this->regionCurrency->isIndiaCurrency($displayCurrency);
        $currency = CurrencyCatalog::meta($displayCurrency);
        $planFeatures = $this->entitlements->currentPlan($subscription)['features'] ?? [];

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
