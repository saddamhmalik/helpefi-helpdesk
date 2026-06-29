<?php

namespace App\Domains\Platform\Observers;

use App\Domains\Platform\Models\MarketingPageContent;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use Illuminate\Support\Facades\Cache;

class MarketingPageContentSitemapCacheObserver
{
    public function saved(MarketingPageContent $content): void
    {
        $this->forget();
    }

    public function deleted(MarketingPageContent $content): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        CentralMarketingPresenter::forgetCache();

        $next = Cache::increment('central:sitemap:version');

        if (! is_int($next)) {
            Cache::put('central:sitemap:version', 2);
        }
    }
}
