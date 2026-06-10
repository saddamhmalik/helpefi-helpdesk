<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Repositories\StripeCatalogRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

class StripeAddonSyncService
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

        foreach ($catalog as $key => $addon) {
            if (! ($addon['enabled'] ?? true)) {
                $synced[$key] = $addon;

                continue;
            }

            try {
                $synced[$key] = $this->syncAddon($key, $addon, $currency);
            } catch (ApiErrorException $exception) {
                throw ValidationException::withMessages([
                    'addons' => "Stripe sync failed for the {$key} add-on: {$exception->getMessage()}",
                ]);
            }
        }

        return $synced;
    }

    private function syncAddon(string $key, array $addon, string $currency): array
    {
        $productId = $this->resolveProductId($key, $addon);
        $this->stripeCatalog->updateProduct($productId, $addon['name'], "addon-{$key}");

        $monthlyPriceId = $this->resolvePriceId(
            $key,
            $addon,
            $productId,
            max(0, (int) ($addon['price_monthly'] ?? 0)) * 100,
            $currency,
        );

        return array_merge($addon, [
            'stripe_product_id' => $productId,
            'stripe_price_id_monthly' => $monthlyPriceId,
        ]);
    }

    private function resolveProductId(string $key, array $addon): string
    {
        $productId = $addon['stripe_product_id'] ?? null;

        if (is_string($productId) && $productId !== '' && $this->stripeCatalog->retrieveProduct($productId)) {
            return $productId;
        }

        $priceId = AddonCatalogDefinition::stripePriceId($addon);

        if ($priceId) {
            $price = $this->stripeCatalog->retrievePrice($priceId);

            if ($price && is_string($price->product)) {
                return $price->product;
            }
        }

        $product = $this->stripeCatalog->createProduct($addon['name'], "addon-{$key}");

        return $product->id;
    }

    private function resolvePriceId(
        string $key,
        array $addon,
        string $productId,
        int $amountCents,
        string $currency,
    ): string {
        $existingPriceId = AddonCatalogDefinition::stripePriceId($addon);

        if (is_string($existingPriceId) && $existingPriceId !== '') {
            $existingPrice = $this->stripeCatalog->retrievePrice($existingPriceId);

            if ($this->priceMatches($existingPrice, $productId, $amountCents, $currency)) {
                return $existingPriceId;
            }

            $this->stripeCatalog->archivePrice($existingPriceId);
        }

        $price = $this->stripeCatalog->createRecurringPrice($productId, $amountCents, $currency, "addon-{$key}", 'month');

        return $price->id;
    }

    private function priceMatches(
        ?object $price,
        string $productId,
        int $amountCents,
        string $currency,
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

        return ($price->recurring->interval ?? null) === 'month';
    }
}
