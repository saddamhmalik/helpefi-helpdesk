<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Repositories\StripeCatalogRepository;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

class StripePlanSyncService
{
    public function __construct(private StripeCatalogRepository $stripeCatalog)
    {
    }

    public function isEnabled(): bool
    {
        return $this->stripeCatalog->isEnabled();
    }

    public function syncCatalog(array $catalog, string $currency): array
    {
        if (! $this->isEnabled()) {
            return $catalog;
        }

        $synced = [];

        foreach ($catalog as $slug => $plan) {
            try {
                $synced[$slug] = $this->syncPlan($slug, $plan, $currency);
            } catch (ApiErrorException $exception) {
                throw ValidationException::withMessages([
                    'plans' => "Stripe sync failed for the {$slug} plan: {$exception->getMessage()}",
                ]);
            }
        }

        return $synced;
    }

    private function syncPlan(string $slug, array $plan, string $currency): array
    {
        $productId = $this->resolveProductId($slug, $plan);
        $this->stripeCatalog->updateProduct($productId, $plan['name'], $slug);

        $monthlyPriceId = $this->resolvePriceId(
            $slug,
            $plan,
            $productId,
            max(0, (int) ($plan['price_monthly'] ?? $plan['price'] ?? 0)) * 100,
            $currency,
            'month',
            'stripe_price_id_monthly',
            'stripe_price_id',
        );

        $yearlyPriceId = $this->resolvePriceId(
            $slug,
            $plan,
            $productId,
            max(0, (int) ($plan['price_yearly'] ?? 0)) * 100,
            $currency,
            'year',
            'stripe_price_id_yearly',
        );

        return array_merge($plan, [
            'stripe_product_id' => $productId,
            'stripe_price_id' => $monthlyPriceId,
            'stripe_price_id_monthly' => $monthlyPriceId,
            'stripe_price_id_yearly' => $yearlyPriceId,
            'price' => (int) ($plan['price_monthly'] ?? $plan['price'] ?? 0),
        ]);
    }

    private function resolveProductId(string $slug, array $plan): string
    {
        $productId = $plan['stripe_product_id'] ?? null;

        if (is_string($productId) && $productId !== '' && $this->stripeCatalog->retrieveProduct($productId)) {
            return $productId;
        }

        foreach (['stripe_price_id_monthly', 'stripe_price_id_yearly', 'stripe_price_id'] as $key) {
            $priceId = $plan[$key] ?? null;

            if (! is_string($priceId) || $priceId === '') {
                continue;
            }

            $price = $this->stripeCatalog->retrievePrice($priceId);

            if ($price && is_string($price->product)) {
                return $price->product;
            }
        }

        $product = $this->stripeCatalog->createProduct($plan['name'], $slug);

        return $product->id;
    }

    private function resolvePriceId(
        string $slug,
        array $plan,
        string $productId,
        int $amountCents,
        string $currency,
        string $interval,
        string $primaryKey,
        ?string $fallbackKey = null,
    ): string {
        $existingPriceId = $plan[$primaryKey] ?? ($fallbackKey ? ($plan[$fallbackKey] ?? null) : null);

        if (is_string($existingPriceId) && $existingPriceId !== '') {
            $existingPrice = $this->stripeCatalog->retrievePrice($existingPriceId);

            if ($this->priceMatches($existingPrice, $productId, $amountCents, $currency, $interval)) {
                return $existingPriceId;
            }

            $this->stripeCatalog->archivePrice($existingPriceId);
        }

        $price = $this->stripeCatalog->createRecurringPrice($productId, $amountCents, $currency, $slug, $interval);

        return $price->id;
    }

    private function priceMatches(
        ?object $price,
        string $productId,
        int $amountCents,
        string $currency,
        string $interval,
    ): bool {
        if ($price === null || ! ($price->active ?? false)) {
            return false;
        }

        if (($price->product ?? null) !== $productId) {
            return false;
        }

        if ((int) ($price->unit_amount ?? -1) !== $amountCents) {
            return false;
        }

        if (strtoupper((string) ($price->currency ?? '')) !== strtoupper($currency)) {
            return false;
        }

        return ($price->recurring->interval ?? null) === $interval;
    }
}
