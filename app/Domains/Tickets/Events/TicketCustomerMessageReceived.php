<?php

namespace App\Domains\Tickets\Events;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCustomerMessageReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public TicketMessage $message,
        public array $context = [],
    ) {
    }
}
