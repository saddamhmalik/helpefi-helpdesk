<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Billing\Services\RazorpayAddonSyncService;
use App\Domains\Billing\Services\RazorpayPlanSyncService;
use App\Domains\Tenancy\Repositories\CentralSettingRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Domains\Tenancy\Support\SocialLinkDefinition;

class CentralSettingsService
{
    public function __construct(
        private CentralSettingRepository $settings,
        private RazorpayPlanSyncService $razorpayPlanSync,
        private RazorpayAddonSyncService $razorpayAddonSync,
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
        return CurrencyCatalog::normalize($this->settings->current()->currency ?? config('billing.currency', 'INR'));
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
        $currency = $this->currencyMeta();

        return collect($this->addonCatalog())
            ->map(fn (array $addon, string $key) => [
                'key' => $key,
                'name' => $addon['name'],
                'feature' => $addon['feature'],
                'description' => $addon['description'],
                'price_monthly' => $addon['price_monthly'],
                'currency' => $currency,
                'enabled' => $addon['enabled'],
                'razorpay_plan_id_monthly' => $addon['razorpay_plan_id_monthly'] ?? null,
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

    public function socialLinks(): array
    {
        $stored = $this->storedSocialLinks();

        return collect(SocialLinkDefinition::platforms())
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'url' => $stored[$key] ?? '',
            ])
            ->filter(fn (array $item) => $item['url'] !== '')
            ->values()
            ->all();
    }

    public function socialLinksForAdmin(): array
    {
        $stored = $this->storedSocialLinks();

        return collect(SocialLinkDefinition::platforms())
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'placeholder' => $meta['placeholder'],
                'url' => $stored[$key] ?? '',
            ])
            ->values()
            ->all();
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
                'razorpay_plan_id' => $plan['razorpay_plan_id_monthly'] ?? null,
                'razorpay_plan_id_monthly' => $plan['razorpay_plan_id_monthly'] ?? null,
                'razorpay_plan_id_yearly' => $plan['razorpay_plan_id_yearly'] ?? null,
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
            'currency' => $this->currencyMeta(),
            'social_links' => $this->socialLinksForAdmin(),
            'razorpay_enabled' => $this->razorpayPlanSync->isEnabled(),
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
            $incoming = $this->normalizeIncomingPlan($plan, $existing);

            $catalog[$slug] = PlanCatalogDefinition::normalizePlan($slug, array_merge($existing, $incoming));
        }

        $currency = isset($data['currency'])
            ? CurrencyCatalog::normalize($data['currency'])
            : $this->currency();

        $catalog = $this->razorpayPlanSync->syncCatalog($catalog, $currency);

        $addonCatalog = [];

        foreach ($data['addons'] ?? [] as $addon) {
            $key = $addon['key'];
            $existing = $this->addonCatalog()[$key] ?? [];
            $incoming = $addon;

            if ($this->razorpayAddonSync->isEnabled()) {
                unset($incoming['razorpay_plan_id_monthly']);
            }

            $addonCatalog[$key] = AddonCatalogDefinition::normalizeAddon($key, array_merge($existing, $incoming));
        }

        foreach ($this->addonCatalog() as $key => $existing) {
            if (! isset($addonCatalog[$key])) {
                $addonCatalog[$key] = $existing;
            }
        }

        $addonCatalog = $this->razorpayAddonSync->syncCatalog($addonCatalog, $currency);

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

        if (array_key_exists('social_links', $data)) {
            $payload['social_links'] = $this->sanitizeSocialLinks($data['social_links']);
        }

        $this->settings->update($this->settings->current(), $payload);

        return $this->snapshot();
    }

    private function storedSocialLinks(): array
    {
        return collect((array) ($this->settings->current()->social_links ?? []))
            ->map(fn ($url) => is_string($url) ? trim($url) : '')
            ->all();
    }

    private function sanitizeSocialLinks(mixed $links): array
    {
        return collect(is_array($links) ? $links : [])
            ->only(SocialLinkDefinition::keys())
            ->map(fn ($url) => is_string($url) ? trim($url) : '')
            ->filter(fn ($url) => $url !== '')
            ->all();
    }

    private function normalizeIncomingPlan(array $plan, array $existing): array
    {
        $incoming = $plan;

        if (array_key_exists('price', $incoming)) {
            $incoming['price_monthly'] = $incoming['price'];
        }

        if ($this->razorpayPlanSync->isEnabled()) {
            unset(
                $incoming['razorpay_plan_id'],
                $incoming['razorpay_plan_id_monthly'],
                $incoming['razorpay_plan_id_yearly'],
            );
        }

        return $incoming;
    }
}
