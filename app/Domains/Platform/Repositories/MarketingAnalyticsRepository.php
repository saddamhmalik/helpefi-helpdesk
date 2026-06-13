<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\MarketingPageView;
use Illuminate\Database\Eloquent\Builder;

class MarketingAnalyticsRepository
{
    public function summary(): array
    {
        $today = now()->startOfDay();
        $last7 = now()->subDays(7);
        $last30 = now()->subDays(30);

        return [
            'total_views' => $this->views()->count(),
            'views_today' => $this->views()->where('visited_at', '>=', $today)->count(),
            'views_7_days' => $this->views()->where('visited_at', '>=', $last7)->count(),
            'views_30_days' => $this->views()->where('visited_at', '>=', $last30)->count(),
            'unique_visitors_today' => $this->views()->where('visited_at', '>=', $today)->distinct()->count('visitor_hash'),
            'unique_visitors_30_days' => $this->views()->where('visited_at', '>=', $last30)->distinct()->count('visitor_hash'),
        ];
    }

    private function views(): Builder
    {
        return MarketingPageView::query()->where('is_bot', false);
    }
}
