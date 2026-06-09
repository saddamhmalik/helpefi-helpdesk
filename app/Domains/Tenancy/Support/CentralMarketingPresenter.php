<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Tenancy\Services\CentralSettingsService;

class CentralMarketingPresenter
{
    public static function shared(): array
    {
        return [
            'brand' => config('app.name', 'Helpdesk'),
            'trialDays' => app(CentralSettingsService::class)->trialDays(),
            'centralDomain' => config('tenancy.central_app_domain'),
            'currency' => app(CentralSettingsService::class)->currencyMeta(),
            'plans' => self::plans(),
        ];
    }

    public static function plans(): array
    {
        return app(CentralSettingsService::class)->plansForDisplay();
    }
}
