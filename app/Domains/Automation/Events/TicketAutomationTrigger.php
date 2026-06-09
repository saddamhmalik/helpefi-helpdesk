<?php

namespace App\Domains\Automation\Events;

use App\Domains\Tickets\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAutomationTrigger
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $trigger,
        public array $context = [],
    ) {
    }
}
