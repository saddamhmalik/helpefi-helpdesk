<?php

namespace App\Domains\Ai\Repositories;

use App\Domains\Tickets\Models\Ticket;

class TicketAiRepository
{
    public function forTicket(int $ticketId): Ticket
    {
        return Ticket::query()
            ->with([
                'contact:id,name,email',
                'status:id,name,slug',
                'priority:id,name,slug',
                'messages' => fn ($query) => $query->with(['user:id,name', 'contact:id,name'])->orderBy('created_at'),
            ])
            ->findOrFail($ticketId);
    }
}
