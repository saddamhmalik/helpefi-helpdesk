<?php

namespace App\Domains\Automation\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Services\AutomationService;

class RunAutomationRules
{
    public function __construct(private AutomationService $automation)
    {
    }

    public function handle(TicketAutomationTrigger $event): void
    {
        if ($event->context['from_automation'] ?? false) {
            return;
        }

        $this->automation->run($event->ticket, $event->trigger, $event->context);
    }
}
