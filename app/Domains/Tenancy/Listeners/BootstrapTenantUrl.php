<?php

namespace App\Domains\Tenancy\Listeners;

use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\TenancyBootstrapped;

class BootstrapTenantUrl
{
    public function handleTenancyBootstrapped(TenancyBootstrapped $event): void
    {
        if (! app()->runningInConsole() && request()->getHost()) {
            URL::forceRootUrl(request()->getSchemeAndHttpHost());

            return;
        }

        $domain = $event->tenancy->tenant?->domains()->value('domain');

        if ($domain) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
            URL::forceRootUrl("{$scheme}://{$domain}");
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
