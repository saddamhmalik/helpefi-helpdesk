<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Assignment\Services\AssignmentService;
use App\Domains\Tickets\Events\TicketUpdated;
use App\Domains\Performance\Services\PerformanceService;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workspace\Services\TicketPresenceService;

class TicketUpdateService
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketStatusLookup $statusLookup,
        private SlaService $sla,
        private AuditRecorder $audit,
        private AssignmentService $assignment,
        private TicketCcService $ticketCcs,
        private TicketPeopleFieldResolver $peopleFields,
        private PerformanceService $performance,
        private TicketPresenceService $presence,
        private TicketRealtimeBroadcaster $realtime,
    ) {
    }

    public function update(int $id, array $data, ?int $userId = null, array $context = []): Ticket
    {
        $ticket = $this->tickets->findForWrite($id);
        [$ccEmails, $requesterEmail, $requesterName] = $this->peopleFields->extractPeopleFields($data);

        if ($requesterEmail !== null || array_key_exists('contact_id', $data)) {
            $data = $this->peopleFields->resolveRequester($data, $requesterEmail, $requesterName, $userId, $ticket);
        }

        $before = $ticket->only(array_keys($data));
        $closingStatus = null;
        $previousPriorityId = isset($data['ticket_priority_id'])
            ? (int) $ticket->ticket_priority_id
            : null;

        if (isset($data['ticket_status_id'])) {
            $closingStatus = $this->statusLookup->closedIds()->contains($data['ticket_status_id']);
            $data['closed_at'] = $closingStatus ? now() : null;
        }

        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = $this->peopleFields->resolveTicketCustomFields($data);
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->peopleFields->normalizeRichText($data['description']);
        }

        $ticket = $this->tickets->update(
            $ticket,
            $this->assignment->enrichUnassignedTicket($this->peopleFields->applyWorkforceRouting($data, $ticket), $ticket),
        );

        if ($ccEmails !== null) {
            $ticket->load('contact');
            $this->ticketCcs->sync($ticket, $ccEmails, $userId);
        }

        if ($closingStatus === true) {
            $this->sla->recordResolution($ticket);

            if ($ticket->assigned_to) {
                $this->performance->record($ticket->assigned_to, 'ticket_resolved', $ticket->id);
            }
        } elseif (
            $previousPriorityId !== null
            && isset($data['ticket_priority_id'])
            && (int) $data['ticket_priority_id'] !== $previousPriorityId
        ) {
            $this->sla->recalculateOnPriorityChange($ticket);
        }

        TicketUpdated::dispatch($ticket, array_merge([
            'changed' => array_keys($data),
        ], $context));

        $this->audit->recordChanges('ticket.updated', $ticket, $before, $ticket->only(array_keys($data)), [
            'number' => $ticket->number,
        ]);

        $this->presence->pulse($ticket->id);

        if (! ($context['autosave'] ?? false)) {
            $this->realtime->broadcastTicketSnapshot($ticket);
        }

        return $ticket;
    }
}
