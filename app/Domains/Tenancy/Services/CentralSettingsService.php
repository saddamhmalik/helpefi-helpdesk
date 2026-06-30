<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Services\RazorpayAddonSyncService;
use App\Domains\Billing\Services\RazorpayPlanSyncService;
use App\Domains\Tenancy\Repositories\CentralSettingRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Domains\Tenancy\Support\SocialLinkDefinition;
use Illuminate\Validation\ValidationException;

class CentralSettingsService
{
    public function __construct(
        private CentralSettingRepository $settings,
        private RazorpayPlanSyncService $razorpayPlanSync,
        private RazorpayAddonSyncService $razorpayAddonSync,
        private SubscriptionRepository $subscriptions,
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

    public function indiaCurrency(): string
    {
        return CurrencyCatalog::normalize(config('billing.india_currency', 'INR'));
    }

    public function indiaCurrencyMeta(): array
    {
        return CurrencyCatalog::meta($this->indiaCurrency());
    }

    public function indiaPricingFlag(): bool
    {
        return (bool) ($this->settings->current()->india_pricing ?? false);
    }

    public function indiaPricingEnabled(): bool
    {
        return $this->indiaPricingFlag()
            && $this->indiaCurrency() !== $this->currency();
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
                'price_monthly_india' => $addon['price_monthly_india'] ?? 0,
                'currency' => $currency,
                'enabled' => $addon['enabled'],
                'razorpay_plan_id_monthly' => $addon['razorpay_plan_id_monthly'] ?? null,
                'razorpay_plan_id_monthly_india' => $addon['razorpay_plan_id_monthly_india'] ?? null,
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
                'custom_pricing' => $plan['custom_pricing'] ?? false,
                'price' => $plan['price_monthly'],
                'price_monthly' => $plan['price_monthly'],
                'price_yearly' => $plan['price_yearly'],
                'price_monthly_india' => $plan['price_monthly_india'] ?? 0,
                'price_yearly_india' => $plan['price_yearly_india'] ?? 0,
                'razorpay_plan_id' => $plan['razorpay_plan_id_monthly'] ?? null,
                'razorpay_plan_id_monthly' => $plan['razorpay_plan_id_monthly'] ?? null,
                'razorpay_plan_id_yearly' => $plan['razorpay_plan_id_yearly'] ?? null,
                'razorpay_plan_id_monthly_india' => $plan['razorpay_plan_id_monthly_india'] ?? null,
                'razorpay_plan_id_yearly_india' => $plan['razorpay_plan_id_yearly_india'] ?? null,
                'limits' => $plan['limits'],
                'features' => array_values(array_unique(array_merge(
                    PlanCatalogDefinition::baselineFeatures(),
                    $plan['features'] ?? [],
                ))),
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
            'india_pricing' => $this->indiaPricingFlag(),
            'india_pricing_effective' => $this->indiaPricingEnabled(),
            'india_currency' => $this->indiaCurrencyMeta(),
            'social_links' => $this->socialLinksForAdmin(),
            'razorpay_enabled' => $this->razorpayPlanSync->isEnabled(),
            'plans' => $this->plansForDisplay(),
            'addons' => $this->addonsForDisplay(),
        ];
    }

    public function update(array $data): array
    {
        $current = $this->settings->current();
        $payload = [];

        if (array_key_exists('trial_days', $data)) {
            $payload['trial_days'] = max(1, min((int) $data['trial_days'], 365));
        }

        if (array_key_exists('tenant_purge_grace_days', $data)) {
            $payload['tenant_purge_grace_days'] = max(1, min((int) $data['tenant_purge_grace_days'], 365));
        }

        if (array_key_exists('tenant_purge_enabled', $data)) {
            $payload['tenant_purge_enabled'] = (bool) $data['tenant_purge_enabled'];
        }

        if (array_key_exists('social_links', $data)) {
            $payload['social_links'] = $this->sanitizeSocialLinks($data['social_links']);
        }

        $currency = isset($data['currency']) ? CurrencyCatalog::normalize($data['currency']) : $this->currency();
        $currencyChanged = isset($data['currency']) && $currency !== $this->currency();

        $indiaEnabled = array_key_exists('india_pricing', $data)
            ? (bool) $data['india_pricing']
            : (bool) ($current->india_pricing ?? false);
        $indiaChanged = array_key_exists('india_pricing', $data)
            && $indiaEnabled !== (bool) ($current->india_pricing ?? false);

        $indiaCurrency = $indiaEnabled && $this->indiaCurrency() !== $currency
            ? $this->indiaCurrency()
            : null;

        if (isset($data['currency'])) {
            $payload['currency'] = $currency;
        }

        if (array_key_exists('india_pricing', $data)) {
            $payload['india_pricing'] = $indiaEnabled;
        }

        if (isset($data['plans']) || $currencyChanged || $indiaChanged) {
            $payload['plan_catalog'] = $this->razorpayPlanSync->syncCatalog(
                $this->resolvePlanCatalog($data['plans'] ?? null),
                $currency,
                $indiaCurrency,
            );
        }

        if (isset($data['addons']) || $currencyChanged || $indiaChanged) {
            $payload['addon_catalog'] = $this->razorpayAddonSync->syncCatalog(
                $this->resolveAddonCatalog($data['addons'] ?? null),
                $currency,
                $indiaCurrency,
            );
        }

        if ($payload !== []) {
            $this->settings->update($current, $payload);
            CentralMarketingPresenter::forgetCache();
        }

        return $this->snapshot();
    }

    public function razorpaySyncWarnings(): array
    {
        return array_merge(
            $this->razorpayPlanSync->skipped(),
            $this->razorpayAddonSync->skipped(),
        );
    }

    private function resolvePlanCatalog(?array $plans): array
    {
        if ($plans === null) {
            return $this->planCatalog();
        }

        $currentCatalog = $this->planCatalog();
        $catalog = [];

        foreach ($plans as $plan) {
            $slug = $plan['slug'];
            $existing = $currentCatalog[$slug] ?? [];
            $incoming = $this->normalizeIncomingPlan($plan, $existing);

            $catalog[$slug] = PlanCatalogDefinition::normalizePlan($slug, array_merge($existing, $incoming));
        }

        $this->guardRemovedPlans(array_keys($currentCatalog), array_keys($catalog));

        return $catalog;
    }

    private function guardRemovedPlans(array $previousSlugs, array $nextSlugs): void
    {
        $defaultSlugs = PlanCatalogDefinition::slugs();
        $removed = array_diff($previousSlugs, $nextSlugs);

        foreach ($removed as $slug) {
            if (in_array($slug, $defaultSlugs, true)) {
                continue;
            }

            $subscribers = $this->subscriptions->countByPlan($slug);

            if ($subscribers > 0) {
                throw ValidationException::withMessages([
                    'plans' => "Cannot remove \"{$slug}\": {$subscribers} workspace(s) are subscribed to it.",
                ]);
            }
        }
    }

    private function resolveAddonCatalog(?array $addons): array
    {
        $catalog = [];

        foreach ($addons ?? [] as $addon) {
            $key = $addon['key'];
            $existing = $this->addonCatalog()[$key] ?? [];
            $incoming = $this->normalizeIncomingAddon($addon);

            $catalog[$key] = AddonCatalogDefinition::normalizeAddon($key, array_merge($existing, $incoming));
        }

        foreach ($this->addonCatalog() as $key => $existing) {
            if (! isset($catalog[$key])) {
                $catalog[$key] = $existing;
            }
        }

        return $catalog;
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

    private function normalizeIncomingAddon(array $addon): array
    {
        $incoming = $addon;

        if (array_key_exists('price_india', $incoming)) {
            $incoming['price_monthly_india'] = $incoming['price_india'];
        }

        if ($this->razorpayAddonSync->isEnabled()) {
            unset(
                $incoming['razorpay_plan_id_monthly'],
                $incoming['razorpay_plan_id_monthly_india'],
            );
        }

        return $incoming;
    }

    private function normalizeIncomingPlan(array $plan, array $existing): array
    {
        $incoming = $plan;

        if (array_key_exists('price', $incoming)) {
            $incoming['price_monthly'] = $incoming['price'];
        }

        if (array_key_exists('price_india', $incoming)) {
            $incoming['price_monthly_india'] = $incoming['price_india'];
        }

        if ($this->razorpayPlanSync->isEnabled()) {
            unset(
                $incoming['razorpay_plan_id'],
                $incoming['razorpay_plan_id_monthly'],
                $incoming['razorpay_plan_id_yearly'],
                $incoming['razorpay_plan_id_monthly_india'],
                $incoming['razorpay_plan_id_yearly_india'],
            );
        }

        return $incoming;
    }
}
