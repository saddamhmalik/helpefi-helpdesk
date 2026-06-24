<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Support\RegionCurrencyResolver;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class BillingService implements FeatureEntitlementChecker
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private PlanEntitlementService $entitlements,
        private BillingSnapshotService $snapshots,
        private CentralSettingsService $centralSettings,
        private RazorpayBillingService $razorpay,
        private RegionCurrencyResolver $regionCurrency,
        private SubscriptionLifecycleService $lifecycle,
    ) {
    }

    public function snapshot(): array
    {
        return $this->snapshots->snapshot();
    }

    public function layoutSnapshot(): array
    {
        return $this->snapshots->layoutSnapshot();
    }

    public function canUseFeature(string $feature): bool
    {
        return $this->entitlements->canUseFeature($feature);
    }

    public function hasAddon(string $addonKey): bool
    {
        return $this->entitlements->hasAddon($addonKey);
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

        if ($feature && in_array($feature, $this->entitlements->currentPlan($subscription)['features'] ?? [], true)) {
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

        if ($feature && in_array($feature, $this->entitlements->currentPlan($subscription)['features'] ?? [], true)) {
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
        return $this->entitlements->withinLimit($key, $buffer);
    }

    public function assertFeature(string $feature): void
    {
        $this->entitlements->assertFeature($feature);
    }

    public function assertLimit(string $key, int $buffer = 0): void
    {
        $this->entitlements->assertLimit($key, $buffer);
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

    public function syncPaymentHistory(string $tenantId): void
    {
        if ($this->razorpay->isEnabled()) {
            $this->razorpay->syncTenantPaymentHistory($tenantId);
        }
    }

    public function syncPaymentHistoryIfStale(string $tenantId, int $ttlSeconds = 900): void
    {
        if (! $this->razorpay->isEnabled()) {
            return;
        }

        $lockKey = "billing:payment_sync:{$tenantId}";

        if (! Cache::add($lockKey, now()->timestamp, $ttlSeconds)) {
            return;
        }

        $this->razorpay->syncTenantPaymentHistory($tenantId);
    }

    public function cancelSubscription(): Subscription
    {
        if ($this->razorpay->isEnabled()) {
            return $this->razorpay->cancelSubscription();
        }

        $subscription = $this->subscriptions->current();

        return $this->lifecycle->markCancelled($subscription, [
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
}
