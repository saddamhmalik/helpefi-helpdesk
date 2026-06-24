<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;

class RazorpayBillingService
{
    public function __construct(
        private RazorpayApiClient $api,
        private RazorpayCheckoutService $checkout,
        private RazorpayAddonBillingService $addons,
        private RazorpayWebhookHandler $webhooks,
        private RazorpayPaymentSyncService $paymentSync,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->api->isEnabled();
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
        return $this->checkout->prepareCheckoutSession(
            $planSlug,
            $customerEmail,
            $customerName,
            $successRedirect,
            $interval,
            $cancelRedirect,
            $currency,
        );
    }

    public function verifySubscriptionPayment(
        string $paymentId,
        string $subscriptionId,
        string $signature,
    ): Subscription {
        return $this->checkout->verifySubscriptionPayment($paymentId, $subscriptionId, $signature);
    }

    public function syncTenantPaymentHistory(string $tenantId): void
    {
        $this->paymentSync->syncTenantPaymentHistory($tenantId);
    }

    public function purchaseAddon(string $addonKey, string $customerEmail, string $customerName = ''): array|Subscription
    {
        return $this->addons->purchaseAddon($addonKey, $customerEmail, $customerName);
    }

    public function cancelAddon(string $addonKey): Subscription
    {
        return $this->addons->cancelAddon($addonKey);
    }

    public function cancelSubscription(): Subscription
    {
        return $this->checkout->cancelSubscription();
    }

    public function handleWebhook(string $payload, ?string $signature): void
    {
        $this->webhooks->handle($payload, $signature);
    }
}
