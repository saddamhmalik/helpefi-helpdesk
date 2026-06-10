<?php

namespace App\Domains\Tenancy\Support;

class AddonCatalogDefinition
{
    public static function defaultAddons(): array
    {
        return config('addon_catalog', []);
    }

    public static function keys(): array
    {
        return array_keys(self::defaultAddons());
    }

    public static function featureForAddon(string $addonKey): ?string
    {
        return self::defaultAddons()[$addonKey]['feature'] ?? null;
    }

    public static function addonForFeature(string $feature): ?string
    {
        foreach (self::defaultAddons() as $key => $addon) {
            if (($addon['feature'] ?? null) === $feature) {
                return $key;
            }
        }

        return null;
    }

    public static function defaultCatalog(): array
    {
        $catalog = [];

        foreach (self::defaultAddons() as $key => $addon) {
            $catalog[$key] = self::normalizeAddon($key, $addon);
        }

        return $catalog;
    }

    public static function normalizeAddon(string $key, array $addon): array
    {
        $defaults = self::defaultAddons()[$key] ?? [];

        return [
            'key' => $key,
            'name' => (string) ($addon['name'] ?? $defaults['name'] ?? ucfirst(str_replace('_', ' ', $key))),
            'feature' => (string) ($addon['feature'] ?? $defaults['feature'] ?? $key),
            'description' => (string) ($addon['description'] ?? $defaults['description'] ?? ''),
            'price_monthly' => max(0, (int) ($addon['price_monthly'] ?? $defaults['price_monthly'] ?? 0)),
            'enabled' => (bool) ($addon['enabled'] ?? true),
            'stripe_product_id' => isset($addon['stripe_product_id']) && $addon['stripe_product_id'] !== ''
                ? (string) $addon['stripe_product_id']
                : null,
            'stripe_price_id_monthly' => isset($addon['stripe_price_id_monthly']) && $addon['stripe_price_id_monthly'] !== ''
                ? (string) $addon['stripe_price_id_monthly']
                : (isset($addon['stripe_price_id']) && $addon['stripe_price_id'] !== '' ? (string) $addon['stripe_price_id'] : null),
        ];
    }

    public static function mergeCatalog(?array $stored): array
    {
        $catalog = self::defaultCatalog();

        if ($stored === null || $stored === []) {
            return $catalog;
        }

        foreach ($catalog as $key => $defaults) {
            if (! isset($stored[$key])) {
                continue;
            }

            $catalog[$key] = self::normalizeAddon($key, array_merge($defaults, $stored[$key]));
        }

        return $catalog;
    }

    public static function stripePriceId(array $addon): ?string
    {
        $priceId = $addon['stripe_price_id_monthly'] ?? $addon['stripe_price_id'] ?? null;

        return $priceId !== null && $priceId !== '' ? (string) $priceId : null;
    }

    public static function forAdminUi(): array
    {
        return collect(self::defaultAddons())
            ->map(fn (array $addon, string $key) => [
                'key' => $key,
                'label' => $addon['name'] ?? ucfirst(str_replace('_', ' ', $key)),
                'description' => $addon['description'] ?? '',
            ])
            ->values()
            ->all();
    }
}
