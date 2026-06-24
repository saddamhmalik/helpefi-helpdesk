<?php

namespace App\Domains\Integrations\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Support\AutomationTriggerGuard;
use App\Domains\Integrations\Services\TicketExternalIssueService;

class SyncExternalIssues
{
    public function __construct(private TicketExternalIssueService $issues)
    {
    }

    public function handle(TicketAutomationTrigger $event): void
    {
        if ($event->trigger !== 'ticket.updated' || AutomationTriggerGuard::shouldSkip($event->context)) {
            return;
        }

        $this->issues->syncOutbound($event->ticket, $event->context);
    }
}
