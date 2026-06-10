<?php

namespace App\Domains\Integrations\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Integrations\Jobs\EnrichTicketFromCrmJob;

class EnrichTicketFromCrm
{
    public function handle(TicketAutomationTrigger $event): void
    {
        if ($event->trigger !== AutomationRule::TRIGGER_TICKET_CREATED) {
            return;
        }

        EnrichTicketFromCrmJob::dispatch($event->ticket->id);
    }
}
