<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\MarketingAnalyticsRepository;
use Illuminate\Support\Facades\Cache;

class MarketingAnalyticsService
{
    public function __construct(private MarketingAnalyticsRepository $analytics)
    {
    }

    public function snapshot(): array
    {
        return Cache::remember(
            'platform:marketing_analytics',
            now()->addMinutes(5),
            fn () => $this->analytics->summary(),
        );
    }
}
