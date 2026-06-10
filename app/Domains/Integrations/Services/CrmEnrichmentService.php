<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Tickets\Models\Ticket;

class CrmEnrichmentService
{
    public function __construct(private CrmProfileService $crmProfiles)
    {
    }

    public function shouldEnrich(): bool
    {
        return $this->crmProfiles->shouldSync();
    }

    public function enrich(Ticket $ticket): ?array
    {
        $ticket->loadMissing('contact');

        if (! $ticket->contact) {
            return null;
        }

        return $this->crmProfiles->syncForContact($ticket->contact);
    }
}
