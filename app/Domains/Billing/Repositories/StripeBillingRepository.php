<?php

namespace App\Domains\Billing\Repositories;

use Stripe\StripeClient;

class StripeBillingRepository
{
    private ?StripeClient $client = null;

    public function isEnabled(): bool
    {
        return (bool) config('stripe.enabled') && config('stripe.secret');
    }

    public function listInvoicesForCustomer(string $customerId, int $limit = 100): array
    {
        $response = $this->client()->invoices->all([
            'customer' => $customerId,
            'limit' => $limit,
        ]);

        return $response->data;
    }

    public function listSubscriptionsForCustomer(string $customerId, int $limit = 20): array
    {
        $response = $this->client()->subscriptions->all([
            'customer' => $customerId,
            'status' => 'all',
            'limit' => $limit,
        ]);

        return $response->data;
    }

    private function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient((string) config('stripe.secret'));
        }

        return $this->client;
    }
}
