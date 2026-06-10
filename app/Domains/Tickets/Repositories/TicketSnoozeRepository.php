<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TicketSnoozeRepository
{
    public function find(int $id): Ticket
    {
        return Ticket::query()->findOrFail($id);
    }

    public function setSnooze(Ticket $ticket, ?Carbon $until): Ticket
    {
        $ticket->update(['snoozed_until' => $until]);

        return $ticket->fresh(['contact', 'status', 'priority', 'assignee']);
    }

    public function expiredSnoozes(): Collection
    {
        return Ticket::query()
            ->whereNotNull('snoozed_until')
            ->where('snoozed_until', '<=', now())
            ->get(['id', 'number', 'subject', 'ticket_status_id', 'ticket_priority_id', 'assigned_to', 'updated_at', 'snoozed_until']);
    }

    public function clearExpired(): int
    {
        return Ticket::query()
            ->whereNotNull('snoozed_until')
            ->where('snoozed_until', '<=', now())
            ->update(['snoozed_until' => null]);
    }
}
