<?php

namespace App\Domains\Platform\Observers;

use App\Domains\Platform\Models\MarketingBlogPost;
use Illuminate\Support\Facades\Cache;

class MarketingBlogPostSitemapCacheObserver
{
    public function saved(MarketingBlogPost $post): void
    {
        $this->forget();
    }

    public function deleted(MarketingBlogPost $post): void
    {
        $this->forget();
    }

    public function restored(MarketingBlogPost $post): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        $next = Cache::increment('central:sitemap:version');

        if (! is_int($next)) {
            Cache::put('central:sitemap:version', 2);
        }
    }
}

