<?php

namespace App\Domains\Integrations\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Integrations\Services\TicketExternalIssueService;

class SyncExternalIssues
{
    public function __construct(private TicketExternalIssueService $issues)
    {
    }

    public function handle(TicketAutomationTrigger $event): void
    {
        if ($event->trigger !== 'ticket.updated') {
            return;
        }

        $this->issues->syncOutbound($event->ticket, $event->context);
    }
}
