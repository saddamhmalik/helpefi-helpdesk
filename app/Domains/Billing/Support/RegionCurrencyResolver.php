<?php

namespace App\Domains\Billing\Support;

use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Support\CurrencyCatalog;
use Illuminate\Http\Request;

class RegionCurrencyResolver
{
    public const OVERRIDE_COOKIE = 'pricing_currency';

    public function __construct(private CentralSettingsService $settings)
    {
    }

    public function resolve(Request $request): string
    {
        $base = $this->settings->currency();

        if (! $this->settings->indiaPricingEnabled()) {
            return $base;
        }

        $india = $this->settings->indiaCurrency();
        $override = strtoupper((string) $request->cookie(self::OVERRIDE_COOKIE, ''));

        if (in_array($override, [$base, $india], true)) {
            return $override;
        }

        return $this->isIndia($request) ? $india : $base;
    }

    public function resolveMeta(Request $request): array
    {
        return CurrencyCatalog::meta($this->resolve($request));
    }

    public function isIndiaCurrency(string $currency): bool
    {
        return $this->settings->indiaPricingEnabled()
            && strtoupper($currency) === $this->settings->indiaCurrency();
    }

    private function isIndia(Request $request): bool
    {
        $country = strtoupper(trim((string) $request->header('CF-IPCountry', '')));

        return $country === strtoupper((string) config('billing.india_country', 'IN'));
    }
}
