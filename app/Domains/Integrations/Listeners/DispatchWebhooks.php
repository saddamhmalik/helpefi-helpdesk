<?php

namespace App\Domains\Integrations\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Automation\Support\AutomationTriggerGuard;
use App\Domains\Integrations\Jobs\DeliverTicketWebhooksJob;

class DispatchWebhooks
{
    public function handle(TicketAutomationTrigger $event): void
    {
        if (AutomationTriggerGuard::shouldSkip($event->context)) {
            return;
        }

        DeliverTicketWebhooksJob::dispatch(
            $event->ticket->id,
            $event->trigger,
            $event->context,
        );
    }
}
