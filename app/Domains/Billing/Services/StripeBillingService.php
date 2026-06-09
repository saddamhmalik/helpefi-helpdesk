<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeBillingService
{
    private ?StripeClient $client = null;

    public function __construct(
        private PlanRepository $plans,
        private SubscriptionRepository $subscriptions,
        private PlatformPaymentService $payments,
        private SubscriptionLifecycleService $lifecycle,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('stripe.enabled') && config('stripe.secret');
    }

    public function checkoutUrl(string $planSlug, string $customerEmail, string $successUrl, string $cancelUrl): string
    {
        $this->assertEnabled();

        $plan = $this->plans->find($planSlug);
        $priceId = $plan['stripe_price_id'] ?? null;

        if (! $priceId) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is not configured for Stripe billing yet.',
            ]);
        }

        $subscription = $this->subscriptions->current();
        $this->assertCanChangePlan($subscription);

        $tenant = Tenant::query()->findOrFail(tenant('id'));
        $customerId = $this->ensureCustomer($tenant, $customerEmail);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'customer_update' => [
                'name' => 'auto',
                'address' => 'auto',
            ],
            'billing_address_collection' => 'required',
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $tenant->id,
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plan' => $planSlug,
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plan' => $planSlug,
                ],
            ],
        ]);

        return $session->url;
    }

    public function portalUrl(string $customerEmail, string $returnUrl): string
    {
        $this->assertEnabled();

        $tenant = Tenant::query()->findOrFail(tenant('id'));
        $customerId = $this->ensureCustomer($tenant, $customerEmail);

        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    public function handleWebhook(string $payload, ?string $signature): void
    {
        $this->assertEnabled();

        $secret = config('stripe.webhook_secret');

        if (! $secret) {
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured.');

            return;
        }

        $event = Webhook::constructEvent($payload, $signature ?? '', $secret);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.paid' => $this->payments->recordFromStripeInvoice($event->data->object),
            'invoice.payment_failed' => $this->payments->recordFailedStripeInvoice($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => null,
        };
    }

    private function handleCheckoutCompleted(Session $session): void
    {
        $tenantId = $session->metadata['tenant_id'] ?? $session->client_reference_id ?? null;
        $plan = $session->metadata['plan'] ?? null;

        if (! $tenantId || ! $plan) {
            return;
        }

        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();

        if (! $subscription) {
            return;
        }

        $stripeSubscriptionId = is_string($session->subscription)
            ? $session->subscription
            : $session->subscription?->id;

        $planData = $this->plans->find($plan);

        $this->lifecycle->restorePaidAccess($subscription, [
            'plan' => $plan,
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'renews_at' => now()->addMonth(),
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_price_id' => $planData['stripe_price_id'] ?? null,
            'cancelled_at' => null,
            'access_ends_at' => null,
        ]);
    }

    private function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $subscription = $this->resolveSubscription($stripeSubscription);

        if (! $subscription) {
            return;
        }

        $this->lifecycle->applyStripeSubscription($subscription, $stripeSubscription);
    }

    private function handleSubscriptionDeleted(object $stripeSubscription): void
    {
        $subscription = $this->resolveSubscription($stripeSubscription);

        if (! $subscription) {
            return;
        }

        $periodEnd = isset($stripeSubscription->current_period_end)
            ? now()->createFromTimestamp($stripeSubscription->current_period_end)
            : null;

        $this->lifecycle->markCancelled($subscription, [
            'stripe_subscription_id' => null,
            'renews_at' => null,
            'cancelled_at' => isset($stripeSubscription->canceled_at)
                ? now()->createFromTimestamp($stripeSubscription->canceled_at)
                : now(),
        ], $periodEnd);
    }

    private function resolveSubscription(object $stripeSubscription): ?Subscription
    {
        $tenantId = $stripeSubscription->metadata->tenant_id ?? null;

        if (! $tenantId) {
            $customerId = is_string($stripeSubscription->customer ?? null) ? $stripeSubscription->customer : null;

            if ($customerId) {
                $tenantId = Tenant::query()->where('stripe_id', $customerId)->value('id');
            }
        }

        if (! $tenantId) {
            return null;
        }

        return Subscription::query()->where('tenant_id', $tenantId)->first();
    }

    private function ensureCustomer(Tenant $tenant, string $email): string
    {
        if ($tenant->stripe_id) {
            return $tenant->stripe_id;
        }

        $customer = $this->client()->customers->create([
            'email' => $email,
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ]);

        $tenant->update(['stripe_id' => $customer->id]);

        return $customer->id;
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
                'plan' => 'Stripe billing is not configured.',
            ]);
        }
    }

    private function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient((string) config('stripe.secret'));
        }

        return $this->client;
    }
}
