<?php

namespace App\Domains\Sla\Observers;

use App\Domains\Reports\Support\DashboardWidgetCache;
use App\Domains\Sla\Models\TicketSlaTimer;

class TicketSlaTimerDashboardCacheObserver
{
    private const BREACH_COLUMNS = [
        'first_response_breached',
        'resolution_breached',
    ];

    public function saved(TicketSlaTimer $timer): void
    {
        if ($timer->wasRecentlyCreated || $timer->wasChanged(self::BREACH_COLUMNS)) {
            DashboardWidgetCache::forget();
        }
    }
}
