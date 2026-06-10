<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Billing\Services\StripeAddonSyncService;
use App\Domains\Billing\Services\StripePlanSyncService;
use App\Domains\Tenancy\Repositories\CentralSettingRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;

class CentralSettingsService
{
    public function __construct(
        private CentralSettingRepository $settings,
        private StripePlanSyncService $stripePlanSync,
        private StripeAddonSyncService $stripeAddonSync,
    ) {
    }

    public function trialDays(): int
    {
        return max(1, $this->settings->current()->trial_days);
    }

    public function tenantPurgeGraceDays(): int
    {
        return max(1, min((int) ($this->settings->current()->tenant_purge_grace_days ?? 15), 365));
    }

    public function tenantPurgeEnabled(): bool
    {
        return (bool) ($this->settings->current()->tenant_purge_enabled ?? true);
    }

    public function currency(): string
    {
        return CurrencyCatalog::normalize($this->settings->current()->currency ?? config('billing.currency', 'USD'));
    }

    public function currencyMeta(): array
    {
        return CurrencyCatalog::meta($this->currency());
    }

    public function planCatalog(): array
    {
        $stored = $this->settings->current()->plan_catalog;

        if ($stored === null && $this->settings->current()->plan_pricing) {
            $stored = PlanCatalogDefinition::catalogFromLegacyPricing($this->settings->current()->plan_pricing);
        }

        return PlanCatalogDefinition::mergeCatalog($stored);
    }

    public function addonCatalog(): array
    {
        $stored = $this->settings->current()->addon_catalog;

        return AddonCatalogDefinition::mergeCatalog($stored);
    }

    public function addonsForDisplay(): array
    {
        return collect($this->addonCatalog())
            ->map(fn (array $addon, string $key) => [
                'key' => $key,
                'name' => $addon['name'],
                'feature' => $addon['feature'],
                'description' => $addon['description'],
                'price_monthly' => $addon['price_monthly'],
                'enabled' => $addon['enabled'],
                'stripe_product_id' => $addon['stripe_product_id'] ?? null,
                'stripe_price_id_monthly' => $addon['stripe_price_id_monthly'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function findAddon(string $key): array
    {
        $catalog = $this->addonCatalog();

        if (! isset($catalog[$key])) {
            throw new \InvalidArgumentException("Unknown add-on [{$key}].");
        }

        return $catalog[$key];
    }

    public function plansForDisplay(): array
    {
        return collect($this->planCatalog())
            ->map(fn (array $plan, string $slug) => [
                'slug' => $slug,
                'name' => $plan['name'],
                'price' => $plan['price_monthly'],
                'price_monthly' => $plan['price_monthly'],
                'price_yearly' => $plan['price_yearly'],
                'stripe_product_id' => $plan['stripe_product_id'] ?? null,
                'stripe_price_id' => $plan['stripe_price_id_monthly'] ?? null,
                'stripe_price_id_monthly' => $plan['stripe_price_id_monthly'] ?? null,
                'stripe_price_id_yearly' => $plan['stripe_price_id_yearly'] ?? null,
                'limits' => $plan['limits'],
                'features' => $plan['features'],
            ])
            ->values()
            ->all();
    }

    public function snapshot(): array
    {
        return [
            'trial_days' => $this->trialDays(),
            'tenant_purge_grace_days' => $this->tenantPurgeGraceDays(),
            'tenant_purge_enabled' => $this->tenantPurgeEnabled(),
            'currency' => $this->currency(),
            'stripe_enabled' => $this->stripePlanSync->isEnabled(),
            'plans' => $this->plansForDisplay(),
            'addons' => $this->addonsForDisplay(),
        ];
    }

    public function update(array $data): array
    {
        $currentCatalog = $this->planCatalog();
        $catalog = [];

        foreach ($data['plans'] as $plan) {
            $slug = $plan['slug'];
            $existing = $currentCatalog[$slug] ?? [];
            $catalog[$slug] = PlanCatalogDefinition::normalizePlan($slug, array_merge($existing, $plan));
        }

        $currency = isset($data['currency'])
            ? CurrencyCatalog::normalize($data['currency'])
            : $this->currency();

        $catalog = $this->stripePlanSync->syncCatalog($catalog, $currency);

        $addonCatalog = [];

        foreach ($data['addons'] ?? [] as $addon) {
            $key = $addon['key'];
            $existing = $this->addonCatalog()[$key] ?? [];
            $addonCatalog[$key] = AddonCatalogDefinition::normalizeAddon($key, array_merge($existing, $addon));
        }

        foreach ($this->addonCatalog() as $key => $existing) {
            if (! isset($addonCatalog[$key])) {
                $addonCatalog[$key] = $existing;
            }
        }

        $addonCatalog = $this->stripeAddonSync->syncCatalog($addonCatalog, $currency);

        $payload = [
            'trial_days' => max(1, min((int) $data['trial_days'], 365)),
            'tenant_purge_grace_days' => max(1, min((int) ($data['tenant_purge_grace_days'] ?? $this->tenantPurgeGraceDays()), 365)),
            'tenant_purge_enabled' => (bool) ($data['tenant_purge_enabled'] ?? $this->tenantPurgeEnabled()),
            'plan_catalog' => $catalog,
            'addon_catalog' => $addonCatalog,
        ];

        if (isset($data['currency'])) {
            $payload['currency'] = $currency;
        }

        $this->settings->update($this->settings->current(), $payload);

        return $this->snapshot();
    }
}
