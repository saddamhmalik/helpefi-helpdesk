<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
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

    public function checkoutUrl(
        string $planSlug,
        string $customerEmail,
        string $successUrl,
        string $cancelUrl,
        string $interval = 'month',
    ): string {
        $this->assertEnabled();

        $plan = $this->plans->find($planSlug);
        $planId = PlanCatalogDefinition::razorpayPlanIdForInterval($plan, $interval);

        if (! $planId) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is not configured for Razorpay billing yet.',
            ]);
        }

        $subscription = $this->subscriptions->current();
        $this->assertCanChangePlan($subscription);

        $tenant = Tenant::query()->findOrFail(tenant('id'));
        $customerId = $this->ensureCustomer($tenant, $customerEmail);

        if ($subscription->razorpay_subscription_id && $subscription->isActive()) {
            $this->client()->subscription->update($subscription->razorpay_subscription_id, [
                'plan_id' => $planId,
                'schedule_change_at' => 'now',
                'customer_notify' => 1,
            ]);

            return $successUrl;
        }

        $razorpaySubscription = $this->client()->subscription->create([
            'plan_id' => $planId,
            'customer_id' => $customerId,
            'customer_notify' => 1,
            'total_count' => $interval === 'year' ? 10 : 120,
            'notes' => [
                'tenant_id' => $tenant->id,
                'plan' => $planSlug,
                'billing_interval' => $interval,
            ],
        ])->toArray();

        $shortUrl = $razorpaySubscription['short_url'] ?? null;

        if (! is_string($shortUrl) || $shortUrl === '') {
            throw ValidationException::withMessages([
                'plan' => 'Unable to start Razorpay checkout. Please try again.',
            ]);
        }

        return $shortUrl;
    }

    public function purchaseAddon(string $addonKey, string $customerEmail): Subscription
    {
        $this->assertEnabled();

        $addon = $this->centralSettings->findAddon($addonKey);
        $planId = AddonCatalogDefinition::razorpayPlanId($addon);

        if (! $planId) {
            throw ValidationException::withMessages([
                'addon' => 'This add-on is not configured for Razorpay billing yet.',
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

        $tenant = Tenant::query()->findOrFail(tenant('id'));
        $this->ensureCustomer($tenant, $customerEmail);

        $addonItem = $this->client()->subscription->createAddon($subscription->razorpay_subscription_id, [
            'item' => [
                'name' => $addon['name'],
                'amount' => max(0, (int) ($addon['price_monthly'] ?? 0)) * 100,
                'currency' => $this->centralSettings->currency(),
            ],
            'quantity' => 1,
        ])->toArray();

        $active = $subscription->active_addons ?? [];
        $razorpayAddonItems = $subscription->razorpay_addon_items ?? [];
        $razorpayAddonItems[$addonKey] = (string) ($addonItem['id'] ?? '');

        return $this->subscriptions->update($subscription, [
            'active_addons' => array_values(array_unique([...$active, $addonKey])),
            'razorpay_addon_items' => $razorpayAddonItems,
        ]);
    }

    public function cancelAddon(string $addonKey): Subscription
    {
        $this->assertEnabled();

        $subscription = $this->subscriptions->current();
        $razorpayAddonItems = $subscription->razorpay_addon_items ?? [];
        $itemId = $razorpayAddonItems[$addonKey] ?? null;

        if ($itemId && $subscription->razorpay_subscription_id) {
            try {
                $this->client()->subscription->deleteAddon($subscription->razorpay_subscription_id, $itemId);
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

        $this->client()->subscription->cancel($subscription->razorpay_subscription_id, [
            'cancel_at_cycle_end' => 1,
        ]);

        $razorpaySubscription = $this->client()->subscription->fetch($subscription->razorpay_subscription_id)->toArray();

        return $this->lifecycle->applyRazorpaySubscription($subscription, $this->normalizeSubscriptionPayload($razorpaySubscription));
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
        $subscription = $this->resolveSubscription($event);

        if (! $subscription) {
            return;
        }

        $payload = $this->normalizeSubscriptionPayload($event['payload']['subscription']['entity'] ?? []);

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
        $subscription = $this->resolveSubscription($event);

        if (! $subscription) {
            return;
        }

        $payload = $this->normalizeSubscriptionPayload($event['payload']['subscription']['entity'] ?? []);
        $periodEnd = isset($payload['current_end']) ? now()->createFromTimestamp($payload['current_end']) : null;

        $this->lifecycle->markCancelled($subscription, [
            'razorpay_subscription_id' => null,
            'renews_at' => null,
            'cancelled_at' => isset($payload['ended_at'])
                ? now()->createFromTimestamp($payload['ended_at'])
                : now(),
        ], $periodEnd);
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
            return $tenant->razorpay_customer_id;
        }

        $customer = $this->client()->customer->create([
            'email' => $email,
            'name' => $tenant->name,
            'notes' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ])->toArray();

        $customerId = (string) ($customer['id'] ?? '');

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

    private function client(): Api
    {
        if ($this->client === null) {
            $this->client = new Api((string) config('razorpay.key'), (string) config('razorpay.secret'));
        }

        return $this->client;
    }
}
