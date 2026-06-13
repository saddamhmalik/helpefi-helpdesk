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

        $counts = $this->views()
            ->selectRaw('COUNT(*) AS total_views')
            ->selectRaw('SUM(CASE WHEN visited_at >= ? THEN 1 ELSE 0 END) AS views_today', [$today])
            ->selectRaw('SUM(CASE WHEN visited_at >= ? THEN 1 ELSE 0 END) AS views_7_days', [$last7])
            ->selectRaw('SUM(CASE WHEN visited_at >= ? THEN 1 ELSE 0 END) AS views_30_days', [$last30])
            ->first();

        return [
            'total_views' => (int) ($counts->total_views ?? 0),
            'views_today' => (int) ($counts->views_today ?? 0),
            'views_7_days' => (int) ($counts->views_7_days ?? 0),
            'views_30_days' => (int) ($counts->views_30_days ?? 0),
            'unique_visitors_today' => $this->uniqueVisitors($today),
            'unique_visitors_30_days' => $this->uniqueVisitors($last30),
        ];
    }

    private function uniqueVisitors(\DateTimeInterface $since): int
    {
        return $this->views()
            ->where('visited_at', '>=', $since)
            ->distinct()
            ->count('visitor_hash');
    }

    private function views(): Builder
    {
        return MarketingPageView::query()->where('is_bot', false);
    }
}
