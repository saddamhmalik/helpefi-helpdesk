<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayWebhookHandler
{
    public function __construct(
        private RazorpayApiClient $api,
        private SubscriptionRepository $subscriptions,
        private PlatformPaymentService $payments,
        private SubscriptionLifecycleService $lifecycle,
    ) {
    }

    public function handle(string $payload, ?string $signature): void
    {
        $this->api->assertEnabled();

        $secret = config('razorpay.webhook_secret');

        if (! $secret) {
            Log::critical('Razorpay webhook rejected: RAZORPAY_WEBHOOK_SECRET is not configured.');

            throw new \RuntimeException('Razorpay webhook secret is not configured.');
        }

        try {
            $this->api->client()->utility->verifyWebhookSignature($payload, $signature ?? '', $secret);
        } catch (SignatureVerificationError $exception) {
            \App\Support\SecurityEventLogger::webhookSignatureFailed('razorpay', $exception->getMessage());

            throw $exception;
        }

        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $eventName = (string) ($event['event'] ?? '');

        if (! $this->shouldProcessEvent($event)) {
            return;
        }

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

        if (! $this->shouldApplySubscriptionState($subscription, $entity, (string) ($event['event'] ?? ''))) {
            return;
        }

        $this->lifecycle->applyRazorpaySubscription($subscription, $this->normalizeSubscriptionPayload($entity));
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

    private function isAddonSubscription(array $entity): bool
    {
        $notes = $entity['notes'] ?? [];

        return ($notes['billing_type'] ?? null) === 'addon'
            || isset($notes['addon_key']);
    }

    private function activateAddonSubscription(Subscription $subscription, string $addonKey, array $addonSubscription): void
    {
        $active = $subscription->active_addons ?? [];
        $items = $subscription->razorpay_addon_items ?? [];
        $items[$addonKey] = (string) ($addonSubscription['id'] ?? '');

        $this->subscriptions->update($subscription, [
            'active_addons' => array_values(array_unique([...$active, $addonKey])),
            'razorpay_addon_items' => $items,
        ]);
    }

    private function shouldProcessEvent(array $event): bool
    {
        $eventId = (string) ($event['id'] ?? '');

        if ($eventId === '') {
            return true;
        }

        $cacheKey = 'razorpay:webhook:'.$eventId;

        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addDays(7));

        return true;
    }

    private function shouldApplySubscriptionState(Subscription $subscription, array $entity, string $eventName): bool
    {
        $status = (string) ($entity['status'] ?? '');

        if ($subscription->status !== Subscription::STATUS_CANCELLED || $subscription->isInGracePeriod()) {
            return true;
        }

        if (! in_array($status, ['active', 'authenticated'], true)) {
            return true;
        }

        if (! str_contains($eventName, 'activated') && ! str_contains($eventName, 'authenticated')) {
            return true;
        }

        $entityCreated = (int) ($entity['created_at'] ?? 0);
        $cancelledAt = $subscription->cancelled_at?->timestamp ?? 0;

        return ! ($entityCreated > 0 && $cancelledAt > 0 && $entityCreated <= $cancelledAt);
    }
}
