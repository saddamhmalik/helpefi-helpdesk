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
        private CentralSettingsService $centralSettings,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('stripe.enabled') && config('stripe.secret');
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
        $priceId = PlanCatalogDefinition::stripePriceIdForInterval($plan, $interval);

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
                'billing_interval' => $interval,
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plan' => $planSlug,
                    'billing_interval' => $interval,
                ],
            ],
        ]);

        return $session->url;
    }

    public function purchaseAddon(string $addonKey, string $customerEmail): Subscription
    {
        $this->assertEnabled();

        $addon = $this->centralSettings->findAddon($addonKey);
        $priceId = AddonCatalogDefinition::stripePriceId($addon);

        if (! $priceId) {
            throw ValidationException::withMessages([
                'addon' => 'This add-on is not configured for Stripe billing yet.',
            ]);
        }

        $subscription = $this->subscriptions->current();

        if ($subscription->isOnTrial()) {
            throw ValidationException::withMessages([
                'addon' => 'Choose a paid plan before purchasing add-ons.',
            ]);
        }

        if (! $subscription->isActive() || ! $subscription->stripe_subscription_id) {
            throw ValidationException::withMessages([
                'addon' => 'Activate a Stripe subscription before purchasing add-ons.',
            ]);
        }

        if (in_array($addonKey, $subscription->active_addons ?? [], true)) {
            return $subscription;
        }

        $tenant = Tenant::query()->findOrFail(tenant('id'));
        $this->ensureCustomer($tenant, $customerEmail);

        $item = $this->client()->subscriptionItems->create([
            'subscription' => $subscription->stripe_subscription_id,
            'price' => $priceId,
            'quantity' => 1,
            'metadata' => [
                'tenant_id' => $tenant->id,
                'addon' => $addonKey,
            ],
        ]);

        $active = $subscription->active_addons ?? [];
        $stripeItems = $subscription->stripe_addon_items ?? [];
        $stripeItems[$addonKey] = $item->id;

        return $this->subscriptions->update($subscription, [
            'active_addons' => array_values(array_unique([...$active, $addonKey])),
            'stripe_addon_items' => $stripeItems,
        ]);
    }

    public function cancelAddon(string $addonKey): Subscription
    {
        $this->assertEnabled();

        $subscription = $this->subscriptions->current();
        $stripeItems = $subscription->stripe_addon_items ?? [];
        $itemId = $stripeItems[$addonKey] ?? null;

        if ($itemId) {
            $this->client()->subscriptionItems->delete($itemId);
        }

        $active = array_values(array_filter(
            $subscription->active_addons ?? [],
            fn (string $key) => $key !== $addonKey,
        ));
        unset($stripeItems[$addonKey]);

        return $this->subscriptions->update($subscription, [
            'active_addons' => $active,
            'stripe_addon_items' => $stripeItems,
        ]);
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
        $interval = $session->metadata['billing_interval'] ?? 'month';

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
        $priceId = PlanCatalogDefinition::stripePriceIdForInterval($planData, $interval);

        $this->lifecycle->restorePaidAccess($subscription, [
            'plan' => $plan,
            'billing_interval' => $interval,
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'renews_at' => $interval === 'year' ? now()->addYear() : now()->addMonth(),
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_price_id' => $priceId,
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
