<?php

namespace App\Domains\Csat\Observers;

use App\Domains\Csat\Services\CsatEmailService;
use App\Domains\Tickets\Models\Ticket;

class TicketCsatObserver
{
    public function __construct(
        private CsatEmailService $csatEmail,
    ) {
    }

    public function updated(Ticket $ticket): void
    {
        if (! $ticket->wasChanged('ticket_status_id')) {
            return;
        }

        $ticket->loadMissing('status');

        if (! $ticket->status?->is_closed) {
            return;
        }

        $this->csatEmail->sendIfEligible($ticket->fresh(['contact', 'status', 'csatResponse']));
    }
}
