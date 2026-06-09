<?php

namespace App\Domains\Integrations\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Integrations\Services\SlackIntegrationService;

class DispatchSlackNotifications
{
    public function __construct(private SlackIntegrationService $slack)
    {
    }

    public function handle(TicketAutomationTrigger $event): void
    {
        $this->slack->notify($event->ticket, $event->trigger, $event->context);
    }
}
