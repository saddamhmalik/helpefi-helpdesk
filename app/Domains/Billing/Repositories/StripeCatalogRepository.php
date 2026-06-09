<?php

namespace App\Domains\Billing\Repositories;

use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeCatalogRepository
{
    private ?StripeClient $client = null;

    public function isEnabled(): bool
    {
        return (bool) config('stripe.enabled') && config('stripe.secret');
    }

    public function retrievePrice(string $priceId): ?object
    {
        try {
            return $this->client()->prices->retrieve($priceId);
        } catch (ApiErrorException) {
            return null;
        }
    }

    public function retrieveProduct(string $productId): ?object
    {
        try {
            return $this->client()->products->retrieve($productId);
        } catch (ApiErrorException) {
            return null;
        }
    }

    public function createProduct(string $name, string $slug): object
    {
        return $this->client()->products->create([
            'name' => $name,
            'metadata' => [
                'plan_slug' => $slug,
                'managed_by' => 'helpdesk',
            ],
        ]);
    }

    public function updateProduct(string $productId, string $name, string $slug): object
    {
        return $this->client()->products->update($productId, [
            'name' => $name,
            'metadata' => [
                'plan_slug' => $slug,
                'managed_by' => 'helpdesk',
            ],
        ]);
    }

    public function createRecurringPrice(string $productId, int $amountCents, string $currency, string $slug): object
    {
        return $this->client()->prices->create([
            'product' => $productId,
            'unit_amount' => $amountCents,
            'currency' => strtolower($currency),
            'recurring' => ['interval' => 'month'],
            'metadata' => [
                'plan_slug' => $slug,
                'managed_by' => 'helpdesk',
            ],
        ]);
    }

    public function archivePrice(string $priceId): void
    {
        $this->client()->prices->update($priceId, ['active' => false]);
    }

    private function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient((string) config('stripe.secret'));
        }

        return $this->client;
    }
}
