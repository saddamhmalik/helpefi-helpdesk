<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Support\RazorpaySubscriptionSupport;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Errors\Error;

class RazorpayAddonBillingService
{
    public function __construct(
        private RazorpayApiClient $api,
        private RazorpayCustomerService $customers,
        private RazorpayCheckoutSessionBuilder $checkoutSessions,
        private SubscriptionRepository $subscriptions,
        private CentralSettingsService $centralSettings,
    ) {
    }

    public function purchaseAddon(string $addonKey, string $customerEmail, string $customerName = ''): array|Subscription
    {
        $this->api->assertEnabled();

        $addon = $this->centralSettings->findAddon($addonKey);

        if (! ($addon['enabled'] ?? true)) {
            throw ValidationException::withMessages([
                'addon' => 'This add-on is not available.',
            ]);
        }

        $subscription = $this->subscriptions->current();

        if ($subscription->isOnTrial()) {
            throw ValidationException::withMessages([
                'addon' => 'Choose a paid plan before purchasing add-ons.',
            ]);
        }

        if (in_array($addonKey, $subscription->active_addons ?? [], true)) {
            return $subscription;
        }

        if (! $subscription->isActive() || ! $subscription->razorpay_subscription_id) {
            throw ValidationException::withMessages([
                'addon' => 'Activate a Razorpay subscription before purchasing add-ons.',
            ]);
        }

        $planId = AddonCatalogDefinition::razorpayPlanIdForRegion(
            $addon,
            $this->isIndiaCurrency((string) ($subscription->currency ?: $this->centralSettings->currency())),
        );

        if (! $planId) {
            throw ValidationException::withMessages([
                'addon' => 'This add-on is not configured for Razorpay billing yet.',
            ]);
        }

        $tenant = Tenant::query()->findOrFail(tenant('id'));

        try {
            $this->customers->ensureCustomer($tenant, $customerEmail);
        } catch (Error $exception) {
            Log::warning('Razorpay customer resolution failed during add-on purchase', [
                'tenant_id' => $tenant->id,
                'addon' => $addonKey,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'addon' => 'Unable to purchase this add-on. Please try again or contact support.',
            ]);
        }

        $existingAddonSubscription = $this->resolvePendingAddonSubscription(
            $subscription,
            $addonKey,
            $addon,
            $customerEmail,
            $customerName,
        );

        if ($existingAddonSubscription !== null) {
            return $existingAddonSubscription;
        }

        try {
            $addonSubscription = $this->api->client()->subscription->create([
                'plan_id' => $planId,
                'customer_id' => $tenant->razorpay_customer_id,
                'customer_notify' => true,
                'total_count' => 120,
                'expire_by' => now()->addDays(3)->getTimestamp(),
                'notes' => [
                    'tenant_id' => $tenant->id,
                    'addon_key' => $addonKey,
                    'billing_type' => 'addon',
                ],
            ])->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay add-on subscription creation failed', [
                'addon' => $addonKey,
                'plan_id' => $planId,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'addon' => 'Unable to purchase this add-on. Please try again or contact support.',
            ]);
        }

        $this->persistPendingAddonSubscription($subscription, $addonKey, $addonSubscription);

        return $this->resolveAddonPurchaseResult(
            $subscription->fresh(),
            $addonKey,
            $addonSubscription,
            $addon,
            $customerEmail,
            $customerName,
        );
    }

    public function cancelAddon(string $addonKey): Subscription
    {
        $this->api->assertEnabled();

        $subscription = $this->subscriptions->current();
        $razorpayAddonItems = $subscription->razorpay_addon_items ?? [];
        $itemId = $razorpayAddonItems[$addonKey] ?? null;

        if ($itemId) {
            try {
                if (str_starts_with($itemId, 'sub_')) {
                    $this->api->fetchedSubscription($itemId)->cancel([
                        'cancel_at_cycle_end' => true,
                    ]);
                } elseif (str_starts_with($itemId, 'ao_')) {
                    $this->api->client()->addon->fetch($itemId)->delete();
                }
            } catch (Error $exception) {
                Log::warning('Razorpay add-on cancellation failed', [
                    'addon' => $addonKey,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $active = array_values(array_filter(
            $subscription->active_addons ?? [],
            fn (string $key) => $key !== $addonKey,
        ));
        unset($razorpayAddonItems[$addonKey]);

        return $this->subscriptions->update($subscription, [
            'active_addons' => $active,
            'razorpay_addon_items' => $razorpayAddonItems,
        ]);
    }

    public function resolveAddonKeyForSubscriptionId(Subscription $subscription, string $subscriptionId): ?string
    {
        foreach ($subscription->razorpay_addon_items ?? [] as $key => $id) {
            if ((string) $id === $subscriptionId) {
                return $key;
            }
        }

        return null;
    }

    public function confirmAddonSubscriptionPayment(
        Subscription $subscription,
        string $subscriptionId,
        string $addonKey,
    ): Subscription {
        try {
            $entity = $this->api->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay add-on subscription fetch failed after payment verification', [
                'subscription_id' => $subscriptionId,
                'addon' => $addonKey,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment was received but the add-on could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        $status = (string) ($entity['status'] ?? '');

        if (! in_array($status, ['active', 'authenticated'], true)) {
            throw ValidationException::withMessages([
                'razorpay' => 'Payment was received but the add-on could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        return $this->activateAddonSubscription($subscription, $addonKey, $entity);
    }

    public function isAddonSubscription(array $entity): bool
    {
        return RazorpaySubscriptionSupport::isAddonSubscription($entity);
    }

    private function activateAddonSubscription(Subscription $subscription, string $addonKey, array $addonSubscription): Subscription
    {
        $active = $subscription->active_addons ?? [];
        $items = $subscription->razorpay_addon_items ?? [];
        $items[$addonKey] = (string) ($addonSubscription['id'] ?? '');

        return $this->subscriptions->update($subscription, [
            'active_addons' => array_values(array_unique([...$active, $addonKey])),
            'razorpay_addon_items' => $items,
        ]);
    }

    private function persistPendingAddonSubscription(
        Subscription $subscription,
        string $addonKey,
        array $addonSubscription,
    ): void {
        $items = $subscription->razorpay_addon_items ?? [];
        $items[$addonKey] = (string) ($addonSubscription['id'] ?? '');

        $this->subscriptions->update($subscription, [
            'razorpay_addon_items' => $items,
        ]);
    }

    private function resolvePendingAddonSubscription(
        Subscription $subscription,
        string $addonKey,
        array $addon,
        string $customerEmail,
        string $customerName,
    ): array|Subscription|null {
        $existingId = $subscription->razorpay_addon_items[$addonKey] ?? null;

        if (! is_string($existingId) || ! str_starts_with($existingId, 'sub_')) {
            return null;
        }

        try {
            $entity = $this->api->fetchedSubscription($existingId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay add-on subscription fetch failed during purchase', [
                'addon' => $addonKey,
                'subscription_id' => $existingId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $status = (string) ($entity['status'] ?? '');

        if (in_array($status, ['active', 'authenticated'], true)) {
            return $this->activateAddonSubscription($subscription, $addonKey, $entity);
        }

        if ($status === 'created') {
            $expectedPlanId = AddonCatalogDefinition::razorpayPlanIdForRegion(
                $addon,
                $this->isIndiaCurrency((string) ($subscription->currency ?: $this->centralSettings->currency())),
            );

            if ($expectedPlanId && ($entity['plan_id'] ?? null) !== $expectedPlanId) {
                return null;
            }

            return $this->checkoutSessions->buildAddonSession($entity, $addon, $customerEmail, $customerName);
        }

        return null;
    }

    private function resolveAddonPurchaseResult(
        Subscription $subscription,
        string $addonKey,
        array $addonSubscription,
        array $addon,
        string $customerEmail,
        string $customerName,
    ): array|Subscription {
        $status = (string) ($addonSubscription['status'] ?? '');

        if (in_array($status, ['active', 'authenticated'], true)) {
            return $this->activateAddonSubscription($subscription, $addonKey, $addonSubscription);
        }

        return $this->checkoutSessions->buildAddonSession($addonSubscription, $addon, $customerEmail, $customerName);
    }

    private function isIndiaCurrency(string $currency): bool
    {
        return $this->centralSettings->indiaPricingEnabled()
            && strtoupper($currency) === $this->centralSettings->indiaCurrency();
    }
}
