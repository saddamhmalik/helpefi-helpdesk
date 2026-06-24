<?php

namespace App\Domains\Tickets\Observers;

use App\Domains\Reports\Support\DashboardWidgetCache;
use App\Domains\Tickets\Models\Ticket;

class TicketDashboardCacheObserver
{
    private const DASHBOARD_COLUMNS = [
        'ticket_status_id',
        'ticket_priority_id',
        'assigned_to',
        'closed_at',
        'merged_into_ticket_id',
    ];

    public function saved(Ticket $ticket): void
    {
        if ($ticket->wasRecentlyCreated || $ticket->wasChanged(self::DASHBOARD_COLUMNS)) {
            DashboardWidgetCache::forget();
        }
    }

    public function deleted(Ticket $ticket): void
    {
        DashboardWidgetCache::forget();
    }
}
