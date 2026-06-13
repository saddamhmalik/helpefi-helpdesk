<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Ai\Services\CentralMarketingAiService;
use App\Domains\Billing\Support\RegionCurrencyResolver;
use App\Domains\Tenancy\Services\CentralSeoService;
use App\Domains\Tenancy\Services\CentralSettingsService;

class CentralMarketingPresenter
{
    public static function shared(): array
    {
        $settings = app(CentralSettingsService::class);

        return [
            'brand' => config('app.name', 'helpefi'),
            'trialDays' => $settings->trialDays(),
            'centralDomain' => config('tenancy.central_app_domain'),
            'currency' => app(RegionCurrencyResolver::class)->resolveMeta(request()),
            'baseCurrency' => $settings->currencyMeta(),
            'indiaCurrency' => $settings->indiaCurrencyMeta(),
            'indiaEnabled' => $settings->indiaPricingEnabled(),
            'socialLinks' => $settings->socialLinks(),
            'plans' => self::plans(),
            'seo' => app(CentralSeoService::class)->shared(),
            'aiDemoEnabled' => app(CentralMarketingAiService::class)->isEnabled(),
        ];
    }

    public static function plans(): array
    {
        return app(CentralSettingsService::class)->plansForDisplay();
    }
}
