<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Ai\Services\CentralMarketingAiService;
use App\Domains\Billing\Support\RegionCurrencyResolver;
use App\Domains\Platform\Services\PlatformTestimonialService;
use App\Domains\Tenancy\Services\CentralSeoService;
use App\Domains\Tenancy\Services\CentralSettingsService;
use Illuminate\Support\Facades\Cache;

class CentralMarketingPresenter
{
    private const CACHE_KEY = 'marketing.shared.base';

    private const CACHE_TTL_SECONDS = 600;

    public static function shared(): array
    {
        return array_merge(self::cachedBase(), [
            'currency' => app(RegionCurrencyResolver::class)->resolveMeta(request()),
        ]);
    }

    public static function forgetCache(): void
    {
        Cache::store('central')->forget(self::CACHE_KEY);
    }

    public static function addons(): array
    {
        return collect(app(CentralSettingsService::class)->addonsForDisplay())
            ->filter(fn (array $addon) => $addon['enabled'] ?? true)
            ->values()
            ->all();
    }

    public static function plans(): array
    {
        return app(CentralSettingsService::class)->plansForDisplay();
    }

    private static function cachedBase(): array
    {
        if (app()->environment('testing')) {
            return self::buildBase();
        }

        return Cache::store('central')->remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => self::buildBase(),
        );
    }

    private static function buildBase(): array
    {
        $settings = app(CentralSettingsService::class);
        $testimonials = app(PlatformTestimonialService::class);

        return [
            'brand' => config('app.name', 'helpefi'),
            'trialDays' => $settings->trialDays(),
            'centralDomain' => config('tenancy.central_app_domain'),
            'contactEmail' => config('marketing_seo.organization.contact_email'),
            'baseCurrency' => $settings->currencyMeta(),
            'indiaCurrency' => $settings->indiaCurrencyMeta(),
            'indiaEnabled' => $settings->indiaPricingEnabled(),
            'socialLinks' => $settings->socialLinks(),
            'plans' => self::plans(),
            'addons' => self::addons(),
            'verticalPages' => VerticalLandingDefinition::forNavigation(),
            'comparePages' => CompareLandingDefinition::forNavigation(),
            'featurePages' => MarketingFeatureDefinition::forNavigation(),
            'blogPosts' => MarketingBlogDefinition::forIndex(),
            'seo' => app(CentralSeoService::class)->shared(),
            'aiDemoEnabled' => app(CentralMarketingAiService::class)->isEnabled(),
            'testimonialsEnabled' => $testimonials->marketingEnabled(),
            'testimonials' => $testimonials->forMarketing(),
        ];
    }
}
