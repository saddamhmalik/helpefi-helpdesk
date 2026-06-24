<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\Billing\Contracts\FeatureEntitlementChecker;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\MajorIncidentRecord;
use App\Domains\ServiceDesk\Repositories\MajorIncidentRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Workforce\Support\AssignableAgentValidator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MajorIncidentService
{
    public function __construct(
        private MajorIncidentRepository $records,
        private TicketRepository $tickets,
        private FeatureEntitlementChecker $entitlements,
        private AuditRecorder $audit,
        private AssignableAgentValidator $assignableAgents,
    ) {
    }

    public function snapshotForTicket(Ticket|int $ticketOrId): ?array
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return null;
        }

        $ticket = $this->resolveTicket($ticketOrId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_INCIDENT) {
            return null;
        }

        $record = $this->records->findForTicket($ticket->id);

        return $this->records->snapshot($record);
    }

    private function resolveTicket(Ticket|int $ticketOrId): Ticket
    {
        return $ticketOrId instanceof Ticket ? $ticketOrId : $this->tickets->find($ticketOrId);
    }

    public function warRoomSnapshot(int $ticketId): ?array
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return null;
        }

        $record = $this->records->findForTicket($ticketId);

        if ($record === null) {
            return null;
        }

        return $this->records->snapshot($record);
    }

    public function declare(int $ticketId, int $userId): array
    {
        $this->entitlements->assertFeature('service_desk');
        $this->assertIncidentTicket($ticketId);

        if ($this->records->findForTicket($ticketId) !== null) {
            throw ValidationException::withMessages([
                'ticket' => 'This incident is already flagged as a major incident.',
            ]);
        }

        $record = $this->records->create([
            'ticket_id' => $ticketId,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
            'declared_by_user_id' => $userId,
            'declared_at' => now(),
            'coordinator_user_ids' => [$userId],
        ]);

        $this->audit->record('service_desk.major_incident_declared', $this->auditTicket($ticketId), [], $userId);

        return $this->records->snapshot($record);
    }

    public function update(int $ticketId, array $data): array
    {
        $this->entitlements->assertFeature('service_desk');
        $record = $this->assertRecord($ticketId);

        if ($record->status === MajorIncidentRecord::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'ticket' => 'This major incident review is already complete.',
            ]);
        }

        $before = $record->only(array_keys($data));

        if (array_key_exists('coordinator_user_ids', $data)) {
            $data['coordinator_user_ids'] = $this->assignableAgents->filter((array) $data['coordinator_user_ids']);
        }

        $updated = $this->records->update($record, $data);

        $this->audit->recordChanges(
            'service_desk.major_incident_updated',
            $this->auditTicket($ticketId),
            $before,
            $updated->only(array_keys($data)),
        );

        return $this->records->snapshot($updated);
    }

    public function resolve(int $ticketId, int $userId): array
    {
        $this->entitlements->assertFeature('service_desk');
        $record = $this->assertRecord($ticketId);

        if ($record->status !== MajorIncidentRecord::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Only active major incidents can be resolved.',
            ]);
        }

        $updated = $this->records->update($record, [
            'status' => MajorIncidentRecord::STATUS_RESOLVED,
            'resolved_by_user_id' => $userId,
            'resolved_at' => now(),
        ]);

        $this->audit->record('service_desk.major_incident_resolved', $this->auditTicket($ticketId), [], $userId);

        return $this->records->snapshot($updated);
    }

    public function completeReview(int $ticketId, array $data, int $userId): array
    {
        $this->entitlements->assertFeature('service_desk');
        $record = $this->assertRecord($ticketId);

        if ($record->status !== MajorIncidentRecord::STATUS_RESOLVED) {
            throw ValidationException::withMessages([
                'status' => 'Resolve the major incident before completing the post-incident review.',
            ]);
        }

        $updated = $this->records->update($record, array_merge($data, [
            'status' => MajorIncidentRecord::STATUS_CLOSED,
            'review_completed_at' => now(),
            'review_completed_by_user_id' => $userId,
        ]));

        $this->audit->record('service_desk.major_incident_review_completed', $this->auditTicket($ticketId), [], $userId);

        return $this->records->snapshot($updated);
    }

    public function activeCount(): int
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return 0;
        }

        return $this->records->dashboardCounts()['active'];
    }

    public function pendingReviewCount(): int
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return 0;
        }

        return $this->records->dashboardCounts()['pending_review'];
    }

    public function dashboardCounts(): array
    {
        if (! $this->entitlements->canUseFeature('service_desk')) {
            return ['active' => 0, 'pending_review' => 0];
        }

        return $this->records->dashboardCounts();
    }

    public function index(): array
    {
        $this->entitlements->assertFeature('service_desk');

        $entries = $this->records->listIndex()
            ->map(fn (MajorIncidentRecord $record) => $this->records->indexEntry($record))
            ->values()
            ->all();

        $counts = $this->records->dashboardCounts();

        return [
            'entries' => $entries,
            'active_count' => $counts['active'],
            'pending_review_count' => $counts['pending_review'],
        ];
    }

    public function activeIncidents(): Collection
    {
        $this->entitlements->assertFeature('service_desk');

        return $this->records->activeIncidents();
    }

    public function assertRecord(int $ticketId): MajorIncidentRecord
    {
        $this->assertIncidentTicket($ticketId);

        $record = $this->records->findForTicket($ticketId);

        if ($record === null) {
            throw ValidationException::withMessages([
                'ticket' => 'Major incident record not found.',
            ]);
        }

        return $record;
    }

    private function assertIncidentTicket(int $ticketId): Ticket
    {
        $ticket = $this->tickets->find($ticketId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_INCIDENT) {
            throw ValidationException::withMessages([
                'ticket' => 'Major incidents can only be declared on incident tickets.',
            ]);
        }

        return $ticket;
    }

    private function auditTicket(int $ticketId): Ticket
    {
        return Ticket::query()->findOrFail($ticketId);
    }
}
