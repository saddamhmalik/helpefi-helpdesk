<?php

namespace App\Domains\Integrations\Jobs;

use App\Domains\Integrations\Services\WebhookService;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverTicketWebhooksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $ticketId,
        public string $event,
        public array $context = [],
    ) {
    }

    public function handle(WebhookService $webhooks): void
    {
        if (! tenant('id')) {
            return;
        }

        $ticket = Ticket::query()->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $webhooks->dispatchForTicket($ticket, $this->event, $this->context);
    }
}
