<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketStatus;
use Illuminate\Database\Eloquent\Collection;

class TicketBulkRepository
{
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
        return TicketStatus::query()->where('slug', 'closed')->firstOrFail();
    }
}
