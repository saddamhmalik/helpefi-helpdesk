<?php

namespace App\Domains\Tenancy\Listeners;

use App\Domains\Tenancy\Services\TenantDomainService;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Events\BootstrappingTenancy;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\TenancyBootstrapped;

class BootstrapTenantUrl
{
    public function handleBootstrappingTenancy(BootstrappingTenancy $event): void
    {
        if (app()->runningInConsole() || ! request()->getHost()) {
            return;
        }

        URL::forceRootUrl(request()->getSchemeAndHttpHost());

        if ($scheme = request()->getScheme()) {
            URL::forceScheme($scheme);
        }
    }

    public function handleTenancyBootstrapped(TenancyBootstrapped $event): void
    {
        if (! app()->runningInConsole() && request()->getHost()) {
            return;
        }

        $rootUrl = app(TenantDomainService::class)->primaryUrl($event->tenancy->tenant);

        if ($rootUrl) {
            URL::forceRootUrl($rootUrl);
        }
    }

    public function handleRevertedToCentralContext(RevertedToCentralContext $event): void
    {
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);

            if ($scheme = parse_url($appUrl, PHP_URL_SCHEME)) {
                URL::forceScheme($scheme);
            }
        }
    }
}
