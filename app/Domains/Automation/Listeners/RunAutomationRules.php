<?php

namespace App\Domains\Automation\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Services\AutomationService;
use App\Domains\Automation\Support\AutomationTriggerGuard;

class RunAutomationRules
{
    public function __construct(private AutomationService $automation)
    {
    }

    public function handle(TicketAutomationTrigger $event): void
    {
        if (AutomationTriggerGuard::shouldSkip($event->context)) {
            return;
        }

        $this->automation->run($event->ticket, $event->trigger, $event->context);
    }
}
