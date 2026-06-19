<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Support\RazorpaySubscriptionCheckout;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayBillingService
{
    private ?Api $client = null;

    public function __construct(
        private PlanRepository $plans,
        private SubscriptionRepository $subscriptions,
        private PlatformPaymentService $payments,
        private SubscriptionLifecycleService $lifecycle,
        private CentralSettingsService $centralSettings,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('razorpay.enabled') && config('razorpay.key') && config('razorpay.secret');
    }

    public function prepareCheckoutSession(
        string $planSlug,
        string $customerEmail,
        string $customerName,
        string $successRedirect,
        string $interval = 'month',
        string $cancelRedirect = '/settings/billing?checkout=cancelled&section=plans',
        ?string $currency = null,
    ): array|string|Subscription {
        $this->assertEnabled();

        $plan = $this->plans->find($planSlug);

        $subscription = $this->subscriptions->current();
        $this->assertCanChangePlan($subscription);

        $currency = $this->resolveCheckoutCurrency($subscription, $currency);
        $india = $this->isIndiaCurrency($currency);
        $planId = PlanCatalogDefinition::razorpayPlanIdForInterval($plan, $interval, $india);

        if (! $planId) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is not configured for '.$currency.' billing yet.',
            ]);
        }

        $tenant = Tenant::query()->findOrFail(tenant('id'));
        try {
            $this->ensureCustomer($tenant, $customerEmail);
        } catch (Error $exception) {
            Log::warning('Razorpay customer creation failed during checkout', [
                'tenant_id' => $tenant->id,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'plan' => 'Unable to start checkout. Please try again or contact support.',
            ]);
        }

        if ($subscription->razorpay_subscription_id) {
            $existing = $this->resolveExistingCheckoutSession(
                $subscription,
                $planSlug,
                $planId,
                $interval,
                $successRedirect,
                $cancelRedirect,
                $plan,
                $customerEmail,
                $customerName,
                $currency,
                $india,
            );

            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            $razorpaySubscription = $this->createRazorpayCheckoutSubscription(
                $tenant,
                $planId,
                $planSlug,
                $interval,
                $currency,
            );
        } catch (Error $exception) {
            Log::warning('Razorpay subscription creation failed during checkout', [
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'plan' => 'Unable to start checkout. Please try again or contact support.',
            ]);
        }

        return $this->beginCheckoutSession(
            $subscription,
            $razorpaySubscription,
            $planSlug,
            $planId,
            $interval,
            $plan,
            $customerEmail,
            $customerName,
            $successRedirect,
            $cancelRedirect,
            $currency,
        );
    }

    private function resolveCheckoutCurrency(Subscription $subscription, ?string $requested): string
    {
        if ($subscription->isActive() && $subscription->currency) {
            return strtoupper($subscription->currency);
        }

        $requested = $requested ? strtoupper($requested) : $this->centralSettings->currency();

        return $this->isIndiaCurrency($requested)
            ? $this->centralSettings->indiaCurrency()
            : $this->centralSettings->currency();
    }

    private function isIndiaCurrency(string $currency): bool
    {
        return $this->centralSettings->indiaPricingEnabled()
            && strtoupper($currency) === $this->centralSettings->indiaCurrency();
    }

    public function verifySubscriptionPayment(
        string $paymentId,
        string $subscriptionId,
        string $signature,
    ): Subscription {
        $this->assertEnabled();

        try {
            $this->client()->utility->verifyPaymentSignature([
                'razorpay_payment_id' => $paymentId,
                'razorpay_subscription_id' => $subscriptionId,
                'razorpay_signature' => $signature,
            ]);
        } catch (SignatureVerificationError $exception) {
            Log::warning('Razorpay payment signature verification failed', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment verification failed. Please try again or contact support.',
            ]);
        }

        $subscription = $this->subscriptions->current();
        $addonKey = $this->resolveAddonKeyForRazorpaySubscriptionId($subscription, $subscriptionId);

        if ($addonKey === null && $subscription->razorpay_subscription_id !== $subscriptionId) {
            try {
                $entity = $this->fetchedSubscription($subscriptionId)->toArray();

                if ($this->isAddonSubscription($entity)) {
                    $addonKey = (string) ($entity['notes']['addon_key'] ?? '');
                }
            } catch (Error) {
                $addonKey = null;
            }
        }

        if ($addonKey !== null && $addonKey !== '') {
            $result = $this->confirmAddonSubscriptionPayment($subscription, $subscriptionId, $addonKey);
        } elseif (is_array($pendingPlanCheckout = session('razorpay_plan_checkout'))
            && ($pendingPlanCheckout['subscription_id'] ?? null) === $subscriptionId) {
            $result = $this->confirmPlanChangeCheckout($subscription, $subscriptionId, $pendingPlanCheckout);
        } else {
            if (! $subscription->razorpay_subscription_id) {
                throw ValidationException::withMessages([
                    'razorpay' => 'No pending checkout session was found for this workspace. Please start checkout again.',
                ]);
            }

            if ($subscription->razorpay_subscription_id !== $subscriptionId) {
                throw ValidationException::withMessages([
                    'razorpay' => 'This checkout session does not match your workspace subscription.',
                ]);
            }

            try {
                $entity = $this->fetchedSubscription($subscriptionId)->toArray();
            } catch (Error $exception) {
                Log::warning('Razorpay subscription fetch failed after payment verification', [
                    'subscription_id' => $subscriptionId,
                    'message' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'razorpay' => 'Payment was received but the subscription could not be confirmed yet. Please refresh billing in a moment.',
                ]);
            }

            $result = $this->lifecycle->applyRazorpaySubscription(
                $subscription,
                $this->normalizeSubscriptionPayload($entity),
            );
        }

        $this->recordVerifiedPayment($paymentId, $subscriptionId);

        return $result;
    }

    public function syncTenantPaymentHistory(string $tenantId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();

        if (! $subscription) {
            return;
        }

        $subscriptionEntities = $this->subscriptionEntitiesForTenant($subscription, $tenantId);
        $syncedPaymentIds = [];

        foreach ($subscriptionEntities as $subscriptionId => $subscriptionEntity) {
            $syncedPaymentIds = array_merge(
                $syncedPaymentIds,
                $this->syncPaymentsFromSubscriptionInvoices($subscriptionId, $subscriptionEntity, $tenantId),
            );
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant?->razorpay_customer_id) {
            $this->syncPaymentsFromCustomer(
                $tenant->razorpay_customer_id,
                $tenantId,
                $subscriptionEntities,
                $syncedPaymentIds,
            );
        }
    }

    /** @return array<string, mixed>|Subscription */
    public function purchaseAddon(string $addonKey, string $customerEmail, string $customerName = ''): array|Subscription
    {
        $this->assertEnabled();

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

        if (! $subscription->isActive() || ! $subscription->razorpay_subscription_id) {
            throw ValidationException::withMessages([
                'addon' => 'Activate a Razorpay subscription before purchasing add-ons.',
            ]);
        }

        if (in_array($addonKey, $subscription->active_addons ?? [], true)) {
            return $subscription;
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
            $this->ensureCustomer($tenant, $customerEmail);
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
            $addonSubscription = $this->client()->subscription->create([
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
        $this->assertEnabled();

        $subscription = $this->subscriptions->current();
        $razorpayAddonItems = $subscription->razorpay_addon_items ?? [];
        $itemId = $razorpayAddonItems[$addonKey] ?? null;

        if ($itemId) {
            try {
                if (str_starts_with($itemId, 'sub_')) {
                    $this->fetchedSubscription($itemId)->cancel([
                        'cancel_at_cycle_end' => true,
                    ]);
                } elseif (str_starts_with($itemId, 'ao_')) {
                    $this->client()->addon->fetch($itemId)->delete();
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

    public function cancelSubscription(): Subscription
    {
        $this->assertEnabled();

        $subscription = $this->subscriptions->current();

        if (! $subscription->razorpay_subscription_id) {
            throw ValidationException::withMessages([
                'subscription' => 'No active Razorpay subscription found.',
            ]);
        }

        $subscriptionId = $subscription->razorpay_subscription_id;

        try {
            $entity = $this->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed before cancellation', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'subscription' => 'Unable to cancel subscription. Please try again or contact support.',
            ]);
        }

        $status = (string) ($entity['status'] ?? '');

        if (in_array($status, ['cancelled', 'completed', 'expired'], true)) {
            return $this->lifecycle->applyRazorpaySubscription(
                $subscription,
                $this->normalizeSubscriptionPayload($entity),
            );
        }

        if ((bool) ($entity['cancel_at_cycle_end'] ?? false) && in_array($status, ['active', 'authenticated'], true)) {
            return $this->lifecycle->applyRazorpaySubscription(
                $subscription,
                $this->normalizeSubscriptionPayload($entity),
            );
        }

        $cancelAtCycleEnd = in_array($status, ['active', 'authenticated'], true);

        try {
            $this->fetchedSubscription($subscriptionId)->cancel([
                'cancel_at_cycle_end' => $cancelAtCycleEnd,
            ]);
        } catch (Error $exception) {
            Log::warning('Razorpay subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'status' => $status,
                'cancel_at_cycle_end' => $cancelAtCycleEnd,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'subscription' => 'Unable to cancel subscription. Please try again or contact support.',
            ]);
        }

        try {
            $razorpaySubscription = $this->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed after cancellation', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'subscription' => 'Subscription cancellation was requested but could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        return $this->lifecycle->applyRazorpaySubscription(
            $subscription,
            $this->normalizeSubscriptionPayload($razorpaySubscription),
        );
    }

    public function handleWebhook(string $payload, ?string $signature): void
    {
        $this->assertEnabled();

        $secret = config('razorpay.webhook_secret');

        if (! $secret) {
            Log::warning('Razorpay webhook received but RAZORPAY_WEBHOOK_SECRET is not configured.');

            return;
        }

        try {
            $this->client()->utility->verifyWebhookSignature($payload, $signature ?? '', $secret);
        } catch (SignatureVerificationError $exception) {
            throw $exception;
        }

        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $eventName = (string) ($event['event'] ?? '');

        match ($eventName) {
            'subscription.authenticated',
            'subscription.activated',
            'subscription.updated' => $this->handleSubscriptionEvent($event),
            'subscription.charged' => $this->handleSubscriptionCharged($event),
            'subscription.pending',
            'subscription.halted' => $this->handleSubscriptionEvent($event),
            'subscription.cancelled',
            'subscription.completed' => $this->handleSubscriptionCancelled($event),
            'payment.failed' => $this->handlePaymentFailed($event),
            default => null,
        };
    }

    private function handleSubscriptionEvent(array $event): void
    {
        $entity = $event['payload']['subscription']['entity'] ?? [];

        if ($this->isAddonSubscription($entity)) {
            $this->handleAddonSubscriptionEvent($event);

            return;
        }

        $subscription = $this->resolveSubscription($event);

        if (! $subscription) {
            return;
        }

        $payload = $this->normalizeSubscriptionPayload($entity);

        $this->lifecycle->applyRazorpaySubscription($subscription, $payload);
    }

    private function handleSubscriptionCharged(array $event): void
    {
        $this->handleSubscriptionEvent($event);

        $payment = $event['payload']['payment']['entity'] ?? null;

        if (is_array($payment)) {
            $this->payments->recordFromRazorpayPayment($payment, $event['payload']['subscription']['entity'] ?? null);
        }
    }

    private function handleSubscriptionCancelled(array $event): void
    {
        $entity = $event['payload']['subscription']['entity'] ?? [];

        if ($this->isAddonSubscription($entity)) {
            $this->handleAddonSubscriptionEvent($event);

            return;
        }

        $subscription = $this->resolveSubscription($event);

        if (! $subscription) {
            return;
        }

        $payload = $this->normalizeSubscriptionPayload($entity);
        $periodEnd = isset($payload->current_end) ? now()->createFromTimestamp($payload->current_end) : null;

        $this->lifecycle->markCancelled($subscription, [
            'razorpay_subscription_id' => null,
            'renews_at' => null,
            'cancelled_at' => isset($payload->ended_at)
                ? now()->createFromTimestamp($payload->ended_at)
                : now(),
        ], $periodEnd);
    }

    private function handleAddonSubscriptionEvent(array $event): void
    {
        $subscription = $this->resolveSubscription($event);

        if (! $subscription) {
            return;
        }

        $entity = $event['payload']['subscription']['entity'] ?? [];
        $addonKey = (string) ($entity['notes']['addon_key'] ?? '');
        $subscriptionId = (string) ($entity['id'] ?? '');
        $status = (string) ($entity['status'] ?? '');

        if ($addonKey === '' || $subscriptionId === '') {
            return;
        }

        if (in_array($status, ['active', 'authenticated'], true)) {
            $this->activateAddonSubscription($subscription, $addonKey, $entity);

            return;
        }

        if (! in_array($status, ['cancelled', 'completed'], true)) {
            return;
        }

        $items = $subscription->razorpay_addon_items ?? [];

        if (($items[$addonKey] ?? null) !== $subscriptionId) {
            return;
        }

        $active = array_values(array_filter(
            $subscription->active_addons ?? [],
            fn (string $key) => $key !== $addonKey,
        ));
        unset($items[$addonKey]);

        $this->subscriptions->update($subscription, [
            'active_addons' => $active,
            'razorpay_addon_items' => $items,
        ]);
    }

    private function handlePaymentFailed(array $event): void
    {
        $payment = $event['payload']['payment']['entity'] ?? null;

        if (! is_array($payment)) {
            return;
        }

        $this->payments->recordFailedRazorpayPayment($payment, $event['payload']['subscription']['entity'] ?? null);
    }

    private function resolveSubscription(array $event): ?Subscription
    {
        $entity = $event['payload']['subscription']['entity'] ?? [];
        $notes = $entity['notes'] ?? [];
        $tenantId = $notes['tenant_id'] ?? null;

        if (! $tenantId) {
            $customerId = $entity['customer_id'] ?? null;

            if (is_string($customerId) && $customerId !== '') {
                $tenantId = Tenant::query()->where('razorpay_customer_id', $customerId)->value('id');
            }
        }

        if (! $tenantId && isset($entity['id'])) {
            return Subscription::query()->where('razorpay_subscription_id', $entity['id'])->first();
        }

        if (! $tenantId) {
            return null;
        }

        return Subscription::query()->where('tenant_id', $tenantId)->first();
    }

    private function normalizeSubscriptionPayload(array $entity): object
    {
        return (object) $entity;
    }

    private function ensureCustomer(Tenant $tenant, string $email): string
    {
        if ($tenant->razorpay_customer_id) {
            try {
                $this->client()->customer->fetch($tenant->razorpay_customer_id);

                return $tenant->razorpay_customer_id;
            } catch (Error $exception) {
                Log::warning('Razorpay customer fetch failed, recreating customer', [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $tenant->razorpay_customer_id,
                    'message' => $exception->getMessage(),
                ]);

                $tenant->update(['razorpay_customer_id' => null]);
            }
        }

        $customer = $this->client()->customer->create([
            'email' => $email,
            'name' => $tenant->name,
            'fail_existing' => '0',
            'notes' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ])->toArray();

        $customerId = (string) ($customer['id'] ?? '');

        if ($customerId === '') {
            throw ValidationException::withMessages([
                'plan' => 'Unable to start checkout. Please try again or contact support.',
            ]);
        }

        $tenant->update(['razorpay_customer_id' => $customerId]);

        return $customerId;
    }

    private function assertCanChangePlan(Subscription $subscription): void
    {
        if ($subscription->isOnTrial()) {
            throw ValidationException::withMessages([
                'plan' => 'Your free trial is still active. Choose a plan after the trial ends.',
            ]);
        }

        if (! $subscription->isTrialExpired() && ! $subscription->isActive() && $subscription->status !== Subscription::STATUS_PAST_DUE) {
            throw ValidationException::withMessages([
                'plan' => 'Activate a subscription plan before changing plans.',
            ]);
        }
    }

    private function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'plan' => 'Razorpay billing is not configured.',
            ]);
        }
    }

    /** @return array<string, mixed>|string|Subscription|null */
    private function resolveExistingCheckoutSession(
        Subscription $subscription,
        string $planSlug,
        string $planId,
        string $interval,
        string $successRedirect,
        string $cancelRedirect,
        array $plan,
        string $customerEmail,
        string $customerName,
        string $currency = '',
        bool $india = false,
    ): array|string|Subscription|null {
        try {
            $entity = $this->fetchedSubscription($subscription->razorpay_subscription_id)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed during checkout', [
                'subscription_id' => $subscription->razorpay_subscription_id,
                'message' => $exception->getMessage(),
            ]);

            $this->clearRazorpaySubscriptionReference($subscription);

            return null;
        }

        $status = (string) ($entity['status'] ?? '');

        if (in_array($status, ['active', 'authenticated'], true)) {
            try {
                $subscription = $this->lifecycle->applyRazorpaySubscription(
                    $subscription,
                    $this->normalizeSubscriptionPayload($entity),
                );
            } catch (\Throwable $exception) {
                Log::warning('Razorpay subscription sync failed during checkout', [
                    'subscription_id' => $subscription->razorpay_subscription_id,
                    'message' => $exception->getMessage(),
                ]);

                $this->clearRazorpaySubscriptionReference($subscription);

                return null;
            }

            if ($subscription->isActive()) {
                if ($this->matchesSelectedPlan($subscription, $planSlug, $interval)) {
                    return $subscription;
                }

                if ($this->planChangeRequiresCheckout($subscription, $interval)) {
                    return null;
                }

                return $this->changeActiveRazorpayPlan(
                    $subscription,
                    $planSlug,
                    $planId,
                    $interval,
                    $entity,
                    $currency,
                    $india,
                );
            }
        }

        if ($status === 'created' && RazorpaySubscriptionCheckout::canAuthenticateViaStandardCheckout($entity)) {
            $pendingInterval = (string) ($entity['notes']['billing_interval'] ?? 'month');

            if (($entity['plan_id'] ?? null) !== $planId || $pendingInterval !== $interval) {
                $this->cancelRazorpaySubscriptionImmediately($subscription->razorpay_subscription_id);
                $this->clearRazorpaySubscriptionReference($subscription);

                return null;
            }

            return $this->buildPlanCheckoutSession(
                $entity,
                $plan,
                $customerEmail,
                $customerName,
                $successRedirect,
                $cancelRedirect,
            );
        }

        if (RazorpaySubscriptionCheckout::shouldResetIncompleteSubscription($entity)) {
            $this->cancelRazorpaySubscriptionImmediately($subscription->razorpay_subscription_id);
        }

        $this->clearRazorpaySubscriptionReference($subscription);

        return null;
    }

    /** @return array<string, mixed> */
    private function buildCheckoutSession(
        array $razorpaySubscription,
        array $plan,
        string $customerEmail,
        string $customerName,
    ): array {
        $subscriptionId = (string) ($razorpaySubscription['id'] ?? '');

        if ($subscriptionId === '') {
            throw ValidationException::withMessages([
                'plan' => 'Unable to start Razorpay checkout. Please try again.',
            ]);
        }

        return [
            'key' => (string) config('razorpay.key'),
            'subscription_id' => $subscriptionId,
            'name' => (string) config('app.name'),
            'description' => ($plan['name'] ?? 'Plan').' subscription',
            'prefill' => [
                'email' => $customerEmail,
                'name' => $customerName,
            ],
            'theme' => [
                'color' => '#2563eb',
            ],
        ];
    }

    private function persistPendingCheckoutSubscription(
        Subscription $subscription,
        array $razorpaySubscription,
        string $planSlug,
        string $planId,
        string $interval,
        string $currency = '',
    ): void {
        $this->subscriptions->update($subscription, array_filter([
            'razorpay_subscription_id' => (string) ($razorpaySubscription['id'] ?? ''),
            'razorpay_plan_id' => $planId,
            'currency' => $currency !== '' ? strtoupper($currency) : null,
        ], static fn ($value) => $value !== null));
    }

    private function matchesSelectedPlan(Subscription $subscription, string $planSlug, string $interval): bool
    {
        return $subscription->plan === $planSlug
            && ($subscription->billing_interval ?? 'month') === $interval;
    }

    private function changeActiveRazorpayPlan(
        Subscription $subscription,
        string $planSlug,
        string $planId,
        string $interval,
        array $currentEntity,
        string $currency = '',
        bool $india = false,
    ): Subscription {
        $subscriptionId = (string) $subscription->razorpay_subscription_id;
        $currentPlan = $this->plans->find($subscription->plan ?? $planSlug);
        $nextPlan = $this->plans->find($planSlug);
        $currentInterval = $subscription->billing_interval ?? 'month';
        $currentPrice = PlanCatalogDefinition::priceForInterval($currentPlan, $currentInterval, $india);
        $nextPrice = PlanCatalogDefinition::priceForInterval($nextPlan, $interval, $india);
        $scheduleChangeAt = $nextPrice >= $currentPrice ? 'now' : 'cycle_end';

        $payload = [
            'plan_id' => $planId,
            'schedule_change_at' => $scheduleChangeAt,
            'customer_notify' => true,
        ];

        if (isset($currentEntity['remaining_count'])) {
            $payload['remaining_count'] = (int) $currentEntity['remaining_count'];
        }

        try {
            $this->fetchedSubscription($subscriptionId)->update($payload);
        } catch (Error $exception) {
            if ($scheduleChangeAt === 'now') {
                try {
                    $payload['schedule_change_at'] = 'cycle_end';
                    $this->fetchedSubscription($subscriptionId)->update($payload);
                } catch (Error $retryException) {
                    Log::warning('Razorpay subscription plan update failed during checkout', [
                        'subscription_id' => $subscriptionId,
                        'plan_id' => $planId,
                        'plan_slug' => $planSlug,
                        'interval' => $interval,
                        'message' => $retryException->getMessage(),
                        'initial_message' => $exception->getMessage(),
                    ]);

                    throw ValidationException::withMessages([
                        'plan' => 'Unable to change plan right now. Please try again or contact support.',
                    ]);
                }
            } else {
                Log::warning('Razorpay subscription plan update failed during checkout', [
                    'subscription_id' => $subscriptionId,
                    'plan_id' => $planId,
                    'plan_slug' => $planSlug,
                    'interval' => $interval,
                    'message' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'plan' => 'Unable to change plan right now. Please try again or contact support.',
                ]);
            }
        }

        $updatedEntity = $this->fetchedSubscription($subscriptionId)->toArray();

        $this->subscriptions->update($subscription, array_filter([
            'plan' => $planSlug,
            'billing_interval' => $interval,
            'razorpay_plan_id' => $planId,
            'currency' => $currency !== '' ? strtoupper($currency) : null,
        ], static fn ($value) => $value !== null));

        return $this->lifecycle->applyRazorpaySubscription(
            $subscription->fresh(),
            $this->normalizeSubscriptionPayload($updatedEntity),
        );
    }

    private function fetchedSubscription(string $subscriptionId)
    {
        return $this->client()->subscription->fetch($subscriptionId);
    }

    private function isAddonSubscription(array $entity): bool
    {
        $notes = $entity['notes'] ?? [];

        return ($notes['billing_type'] ?? null) === 'addon'
            || isset($notes['addon_key']);
    }

    private function resolveAddonKeyForRazorpaySubscriptionId(Subscription $subscription, string $subscriptionId): ?string
    {
        foreach ($subscription->razorpay_addon_items ?? [] as $key => $id) {
            if ((string) $id === $subscriptionId) {
                return $key;
            }
        }

        return null;
    }

    private function confirmAddonSubscriptionPayment(
        Subscription $subscription,
        string $subscriptionId,
        string $addonKey,
    ): Subscription {
        try {
            $entity = $this->fetchedSubscription($subscriptionId)->toArray();
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

    /** @return array<string, mixed>|Subscription|null */
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
            $entity = $this->fetchedSubscription($existingId)->toArray();
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

            return $this->buildAddonCheckoutSession($entity, $addon, $customerEmail, $customerName);
        }

        return null;
    }

    /** @return array<string, mixed>|Subscription */
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

        return $this->buildAddonCheckoutSession($addonSubscription, $addon, $customerEmail, $customerName);
    }

    /** @return array<string, mixed> */
    private function buildPlanCheckoutSession(
        array $razorpaySubscription,
        array $plan,
        string $customerEmail,
        string $customerName,
        string $successRedirect = '/settings/billing?checkout=success&section=plans',
        string $cancelRedirect = '/settings/billing?checkout=cancelled&section=plans',
    ): array {
        return array_merge(
            $this->buildCheckoutSession($razorpaySubscription, $plan, $customerEmail, $customerName),
            [
                'redirect_on_success' => $successRedirect,
                'redirect_on_cancel' => $cancelRedirect,
            ],
        );
    }

    private function planChangeRequiresCheckout(Subscription $subscription, string $interval): bool
    {
        if (! $subscription->isActive() || ! $subscription->razorpay_subscription_id) {
            return true;
        }

        return ($subscription->billing_interval ?? 'month') !== $interval;
    }

    /** @return array<string, mixed> */
    private function createRazorpayCheckoutSubscription(
        Tenant $tenant,
        string $planId,
        string $planSlug,
        string $interval,
        string $currency = '',
    ): array {
        return $this->client()->subscription->create([
            'plan_id' => $planId,
            'customer_id' => $tenant->razorpay_customer_id,
            'customer_notify' => true,
            'total_count' => $interval === 'year' ? 10 : 120,
            'expire_by' => now()->addDays(3)->getTimestamp(),
            'notes' => [
                'tenant_id' => $tenant->id,
                'plan' => $planSlug,
                'billing_interval' => $interval,
                'currency' => $currency,
            ],
        ])->toArray();
    }

    /** @return array<string, mixed> */
    private function beginCheckoutSession(
        Subscription $subscription,
        array $razorpaySubscription,
        string $planSlug,
        string $planId,
        string $interval,
        array $plan,
        string $customerEmail,
        string $customerName,
        string $successRedirect,
        string $cancelRedirect,
        string $currency = '',
    ): array {
        $previousSubscriptionId = $subscription->isActive() && $subscription->razorpay_subscription_id
            ? (string) $subscription->razorpay_subscription_id
            : null;

        if ($previousSubscriptionId) {
            session([
                'razorpay_plan_checkout' => [
                    'subscription_id' => (string) ($razorpaySubscription['id'] ?? ''),
                    'previous_subscription_id' => $previousSubscriptionId,
                    'plan' => $planSlug,
                    'plan_id' => $planId,
                    'interval' => $interval,
                    'currency' => $currency,
                ],
            ]);
        } else {
            $this->persistPendingCheckoutSubscription($subscription, $razorpaySubscription, $planSlug, $planId, $interval, $currency);
        }

        return $this->buildPlanCheckoutSession(
            $razorpaySubscription,
            $plan,
            $customerEmail,
            $customerName,
            $successRedirect,
            $cancelRedirect,
        );
    }

    private function confirmPlanChangeCheckout(
        Subscription $subscription,
        string $subscriptionId,
        array $pendingPlanCheckout,
    ): Subscription {
        try {
            $entity = $this->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed after plan change checkout', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'razorpay' => 'Payment was received but the subscription could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        $status = (string) ($entity['status'] ?? '');

        if (! in_array($status, ['active', 'authenticated'], true)) {
            throw ValidationException::withMessages([
                'razorpay' => 'Payment was received but the subscription could not be confirmed yet. Please refresh billing in a moment.',
            ]);
        }

        $previousId = (string) ($pendingPlanCheckout['previous_subscription_id'] ?? '');

        if ($previousId !== '') {
            try {
                $this->fetchedSubscription($previousId)->cancel([
                    'cancel_at_cycle_end' => true,
                ]);
            } catch (Error $exception) {
                Log::warning('Razorpay previous subscription cancellation failed after plan change checkout', [
                    'subscription_id' => $previousId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        session()->forget('razorpay_plan_checkout');

        $this->subscriptions->update($subscription, array_filter([
            'plan' => (string) ($pendingPlanCheckout['plan'] ?? $subscription->plan),
            'billing_interval' => (string) ($pendingPlanCheckout['interval'] ?? $subscription->billing_interval ?? 'month'),
            'razorpay_subscription_id' => $subscriptionId,
            'razorpay_plan_id' => (string) ($pendingPlanCheckout['plan_id'] ?? $entity['plan_id'] ?? ''),
            'currency' => ($pendingPlanCheckout['currency'] ?? '') !== '' ? strtoupper((string) $pendingPlanCheckout['currency']) : null,
        ], static fn ($value) => $value !== null && $value !== ''));

        return $this->lifecycle->applyRazorpaySubscription(
            $subscription->fresh(),
            $this->normalizeSubscriptionPayload($entity),
        );
    }

    /** @return array<string, mixed> */
    private function buildAddonCheckoutSession(
        array $addonSubscription,
        array $addon,
        string $customerEmail,
        string $customerName,
    ): array {
        return array_merge(
            $this->buildCheckoutSession(
                $addonSubscription,
                ['name' => $addon['name'] ?? 'Add-on'],
                $customerEmail,
                $customerName,
            ),
            [
                'redirect_on_success' => '/settings/billing?checkout=success&section=addons',
                'redirect_on_cancel' => '/settings/billing?checkout=cancelled&section=addons',
            ],
        );
    }

    private function cancelRazorpaySubscriptionImmediately(string $subscriptionId): void
    {
        try {
            $this->fetchedSubscription($subscriptionId)->cancel([
                'cancel_at_cycle_end' => false,
            ]);
        } catch (Error $exception) {
            Log::warning('Razorpay subscription cancellation failed during checkout reset', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function clearRazorpaySubscriptionReference(Subscription $subscription): void
    {
        if (! $subscription->razorpay_subscription_id) {
            return;
        }

        $this->subscriptions->update($subscription, [
            'razorpay_subscription_id' => null,
            'razorpay_plan_id' => null,
        ]);
    }

    private function recordVerifiedPayment(string $paymentId, string $subscriptionId): void
    {
        try {
            $payment = $this->client()->payment->fetch($paymentId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay payment fetch failed after checkout verification', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
                'tenant_id' => tenant('id'),
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (! in_array($payment['status'] ?? '', ['captured', 'authorized'], true)) {
            return;
        }

        try {
            $subscriptionEntity = $this->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            $subscriptionEntity = [
                'id' => $subscriptionId,
                'notes' => ['tenant_id' => (string) tenant('id')],
            ];
        }

        if (empty($subscriptionEntity['notes']['tenant_id'])) {
            $subscriptionEntity['notes']['tenant_id'] = (string) tenant('id');
        }

        $this->payments->recordFromRazorpayPayment($payment, $subscriptionEntity);
    }

    private function subscriptionEntitiesForTenant(?Subscription $subscription, string $tenantId): array
    {
        if (! $subscription) {
            return [];
        }

        $subscriptionIds = array_values(array_filter(array_unique(array_merge(
            $subscription->razorpay_subscription_id ? [(string) $subscription->razorpay_subscription_id] : [],
            array_map('strval', array_values($subscription->razorpay_addon_items ?? [])),
        ))));

        $entities = [];

        foreach ($subscriptionIds as $subscriptionId) {
            $entity = $this->fetchSubscriptionEntityForSync($subscriptionId, $tenantId);

            if ($entity !== null) {
                $entities[$subscriptionId] = $entity;
            }
        }

        return $entities;
    }

    private function fetchSubscriptionEntityForSync(string $subscriptionId, string $tenantId): ?array
    {
        try {
            $entity = $this->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay subscription fetch failed during payment sync', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => $tenantId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (empty($entity['notes']['tenant_id'])) {
            $entity['notes']['tenant_id'] = $tenantId;
        }

        return $entity;
    }

    private function syncPaymentsFromSubscriptionInvoices(string $subscriptionId, array $subscriptionEntity, string $tenantId): array
    {
        try {
            $response = $this->client()->invoice->all([
                'subscription_id' => $subscriptionId,
                'count' => 100,
            ])->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay invoice list failed during payment sync', [
                'subscription_id' => $subscriptionId,
                'tenant_id' => $tenantId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $syncedPaymentIds = [];

        foreach ($response['items'] ?? [] as $invoice) {
            if (! is_array($invoice) || ($invoice['status'] ?? '') !== 'paid') {
                continue;
            }

            $paymentId = (string) ($invoice['payment_id'] ?? '');

            if ($paymentId === '') {
                continue;
            }

            if ($this->recordSyncedPayment($paymentId, $subscriptionEntity, $tenantId)) {
                $syncedPaymentIds[] = $paymentId;
            }
        }

        return $syncedPaymentIds;
    }

    private function syncPaymentsFromCustomer(
        string $customerId,
        string $tenantId,
        array $subscriptionEntities,
        array $syncedPaymentIds,
    ): void {
        try {
            $response = $this->client()->payment->all([
                'customer_id' => $customerId,
                'count' => 100,
            ])->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay payment list failed during payment sync', [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($response['items'] ?? [] as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $paymentId = (string) ($payment['id'] ?? '');

            if ($paymentId === '' || in_array($paymentId, $syncedPaymentIds, true)) {
                continue;
            }

            if (! in_array($payment['status'] ?? '', ['captured', 'authorized'], true)) {
                continue;
            }

            $subscriptionId = (string) ($payment['subscription_id'] ?? '');
            $subscriptionEntity = $subscriptionId !== ''
                ? ($subscriptionEntities[$subscriptionId] ?? $this->fetchSubscriptionEntityForSync($subscriptionId, $tenantId))
                : ['notes' => ['tenant_id' => $tenantId]];

            if ($subscriptionEntity === null) {
                $subscriptionEntity = ['notes' => ['tenant_id' => $tenantId]];
            }

            $this->payments->recordFromRazorpayPayment($payment, $subscriptionEntity);
        }
    }

    private function recordSyncedPayment(string $paymentId, array $subscriptionEntity, string $tenantId): bool
    {
        try {
            $payment = $this->client()->payment->fetch($paymentId)->toArray();
        } catch (Error $exception) {
            Log::warning('Razorpay payment fetch failed during payment sync', [
                'payment_id' => $paymentId,
                'tenant_id' => $tenantId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! in_array($payment['status'] ?? '', ['captured', 'authorized'], true)) {
            return false;
        }

        $this->payments->recordFromRazorpayPayment($payment, $subscriptionEntity);

        return true;
    }

    private function client(): Api
    {
        if ($this->client === null) {
            $this->client = new Api((string) config('razorpay.key'), (string) config('razorpay.secret'));
        }

        return $this->client;
    }
}
