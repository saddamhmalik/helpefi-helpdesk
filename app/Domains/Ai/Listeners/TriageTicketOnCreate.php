<?php

namespace App\Domains\Ai\Listeners;

use App\Domains\Ai\Jobs\TriageTicketJob;
use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Models\AutomationRule;

class TriageTicketOnCreate
{
    public function handle(TicketAutomationTrigger $event): void
    {
        if ($event->trigger !== AutomationRule::TRIGGER_TICKET_CREATED) {
            return;
        }

        TriageTicketJob::dispatch($event->ticket->id);
    }
}
