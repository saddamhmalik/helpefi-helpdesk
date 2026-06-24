<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketStatusLookup;
use Illuminate\Database\Eloquent\Collection;

class TicketBulkRepository
{
    public function __construct(private TicketStatusLookup $statusLookup)
    {
    }

    public function findByIds(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Ticket::query()
            ->whereIn('id', $ids)
            ->whereNull('merged_into_ticket_id')
            ->get();
    }

    public function closedStatus(): TicketStatus
    {
        return $this->statusLookup->defaultClosed();
    }
}
