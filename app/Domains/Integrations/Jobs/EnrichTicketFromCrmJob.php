<?php

namespace App\Domains\Integrations\Jobs;

use App\Domains\Integrations\Services\CrmEnrichmentService;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichTicketFromCrmJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ticketId)
    {
    }

    public function handle(CrmEnrichmentService $enrichment, TicketRepository $tickets): void
    {
        if (! $enrichment->shouldEnrich()) {
            return;
        }

        $ticket = $tickets->find($this->ticketId);
        $ticket->loadMissing('contact');

        $enrichment->enrich($ticket);
    }
}
