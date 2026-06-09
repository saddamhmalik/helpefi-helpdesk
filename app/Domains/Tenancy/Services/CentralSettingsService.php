<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Billing\Services\StripePlanSyncService;
use App\Domains\Tenancy\Repositories\CentralSettingRepository;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;

class CentralSettingsService
{
    public function __construct(
        private CentralSettingRepository $settings,
        private StripePlanSyncService $stripePlanSync,
    ) {
    }

    public function trialDays(): int
    {
        return max(1, $this->settings->current()->trial_days);
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

    public function plansForDisplay(): array
    {
        return collect($this->planCatalog())
            ->map(fn (array $plan, string $slug) => [
                'slug' => $slug,
                'name' => $plan['name'],
                'price' => $plan['price'],
                'stripe_product_id' => $plan['stripe_product_id'] ?? null,
                'stripe_price_id' => $plan['stripe_price_id'] ?? null,
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
            'currency' => $this->currency(),
            'stripe_enabled' => $this->stripePlanSync->isEnabled(),
            'plans' => $this->plansForDisplay(),
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

        $payload = [
            'trial_days' => max(1, min((int) $data['trial_days'], 365)),
            'plan_catalog' => $catalog,
        ];

        if (isset($data['currency'])) {
            $payload['currency'] = $currency;
        }

        $this->settings->update($this->settings->current(), $payload);

        return $this->snapshot();
    }
}
