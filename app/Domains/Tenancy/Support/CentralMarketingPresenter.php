<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Ai\Services\CentralMarketingAiService;
use App\Domains\Tenancy\Services\CentralSeoService;
use App\Domains\Tenancy\Services\CentralSettingsService;

class CentralMarketingPresenter
{
    public static function shared(): array
    {
        return [
            'brand' => config('app.name', 'helpefi'),
            'trialDays' => app(CentralSettingsService::class)->trialDays(),
            'centralDomain' => config('tenancy.central_app_domain'),
            'currency' => app(CentralSettingsService::class)->currencyMeta(),
            'socialLinks' => app(CentralSettingsService::class)->socialLinks(),
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
