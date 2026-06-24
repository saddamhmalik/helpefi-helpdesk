<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;

class TicketSplitService
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketSplitPersistence $splitPersistence,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
        private TicketRealtimeBroadcaster $realtime,
    ) {
    }

    public function split(int $id, int $fromMessageId, int $userId, ?string $subject = null): Ticket
    {
        $this->entitlements->assertLimit('tickets_monthly', 1);

        $source = $this->tickets->find($id);
        $newTicket = $this->splitPersistence->execute($source, $fromMessageId, $userId, $subject);

        $this->audit->record('ticket.split', $newTicket, [
            'from_ticket_number' => $source->number,
            'from_ticket_id' => $source->id,
            'from_message_id' => $fromMessageId,
        ], $userId);

        $this->realtime->broadcastTicketSnapshot($this->tickets->findForBroadcast($newTicket->id));

        return $newTicket;
    }
}
