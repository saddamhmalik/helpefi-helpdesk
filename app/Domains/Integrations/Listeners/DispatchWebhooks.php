<?php

namespace App\Domains\Integrations\Listeners;

use App\Domains\Automation\Events\TicketAutomationTrigger;
use App\Domains\Integrations\Services\WebhookService;

class DispatchWebhooks
{
    public function __construct(private WebhookService $webhooks)
    {
    }

    public function handle(TicketAutomationTrigger $event): void
    {
        $this->webhooks->dispatchForTicket(
            $event->ticket,
            $event->trigger,
            $event->context,
        );
    }
}
