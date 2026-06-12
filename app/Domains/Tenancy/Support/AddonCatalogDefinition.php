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
            'razorpay_plan_id_monthly' => isset($addon['razorpay_plan_id_monthly']) && $addon['razorpay_plan_id_monthly'] !== ''
                ? (string) $addon['razorpay_plan_id_monthly']
                : null,
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

    public static function razorpayPlanId(array $addon): ?string
    {
        $planId = $addon['razorpay_plan_id_monthly'] ?? null;

        return $planId !== null && $planId !== '' ? (string) $planId : null;
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
