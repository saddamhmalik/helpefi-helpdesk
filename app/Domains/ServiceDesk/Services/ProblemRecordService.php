<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ProblemRecord;
use App\Domains\ServiceDesk\Repositories\ProblemRecordRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProblemRecordService
{
    public function __construct(
        private ProblemRecordRepository $records,
        private TicketRepository $tickets,
        private BillingService $billing,
        private AuditRecorder $audit,
    ) {
    }

    public function ensureForTicket(Ticket $ticket): ?ProblemRecord
    {
        if (! $this->billing->canUseFeature('service_desk')) {
            return null;
        }

        if ($ticket->type !== ServiceCatalogItem::TYPE_PROBLEM) {
            return null;
        }

        return $this->records->findOrCreateForTicket($ticket->id);
    }

    public function snapshotForTicket(int $ticketId): ?array
    {
        if (! $this->billing->canUseFeature('service_desk')) {
            return null;
        }

        $ticket = $this->tickets->find($ticketId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_PROBLEM) {
            return null;
        }

        $record = $this->records->findOrCreateForTicket($ticketId);

        return $this->records->snapshot($record, $ticketId);
    }

    public function update(int $ticketId, array $data): array
    {
        $this->billing->assertFeature('service_desk');
        $this->assertProblemTicket($ticketId);
        $record = $this->records->findOrCreateForTicket($ticketId);
        $before = $record->only(array_keys($data));
        $updated = $this->records->update($record, $data);

        $this->audit->recordChanges(
            'service_desk.problem_record_updated',
            $this->tickets->find($ticketId),
            $before,
            $updated->only(array_keys($data)),
        );

        return $this->records->snapshot($updated, $ticketId);
    }

    public function linkIncident(int $problemTicketId, int $incidentTicketId, ?int $userId): array
    {
        $this->billing->assertFeature('service_desk');
        $this->assertProblemTicket($problemTicketId);
        $incident = $this->tickets->find($incidentTicketId);

        if ($incident->type !== ServiceCatalogItem::TYPE_INCIDENT) {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'Only incident tickets can be linked to a problem.',
            ]);
        }

        if ($this->records->linkExists($problemTicketId, $incidentTicketId)) {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'This incident is already linked.',
            ]);
        }

        $this->records->createLink($problemTicketId, $incidentTicketId, $userId);

        $problemTicket = $this->tickets->find($problemTicketId);

        $this->audit->record('service_desk.problem_incident_linked', $problemTicket, [
            'incident_ticket_id' => $incidentTicketId,
            'incident_number' => $incident->number,
        ], $userId);

        $this->audit->record('service_desk.incident_linked_to_problem', $incident, [
            'problem_ticket_id' => $problemTicketId,
            'problem_number' => $problemTicket->number,
        ], $userId);

        return $this->snapshotForTicket($problemTicketId);
    }

    public function unlinkIncident(int $problemTicketId, int $incidentTicketId): array
    {
        $this->billing->assertFeature('service_desk');
        $this->assertProblemTicket($problemTicketId);

        $problemTicket = $this->tickets->find($problemTicketId);
        $incident = $this->tickets->find($incidentTicketId);

        if (! $this->records->deleteLink($problemTicketId, $incidentTicketId)) {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'Incident link not found.',
            ]);
        }

        $this->audit->record('service_desk.problem_incident_unlinked', $problemTicket, [
            'incident_ticket_id' => $incidentTicketId,
            'incident_number' => $incident->number,
        ]);

        $this->audit->record('service_desk.incident_unlinked_from_problem', $incident, [
            'problem_ticket_id' => $problemTicketId,
            'problem_number' => $problemTicket->number,
        ]);

        return $this->snapshotForTicket($problemTicketId);
    }

    public function incidentCandidates(int $problemTicketId): Collection
    {
        $this->billing->assertFeature('service_desk');
        $this->assertProblemTicket($problemTicketId);

        return $this->records->incidentCandidates($problemTicketId);
    }

    private function assertProblemTicket(int $ticketId): Ticket
    {
        $ticket = $this->tickets->find($ticketId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_PROBLEM) {
            throw ValidationException::withMessages([
                'ticket' => 'Problem details are only available on problem tickets.',
            ]);
        }

        return $ticket;
    }
}
