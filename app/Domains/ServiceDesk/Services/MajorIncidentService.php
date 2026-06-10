<?php

namespace App\Domains\ServiceDesk\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\MajorIncidentRecord;
use App\Domains\ServiceDesk\Repositories\MajorIncidentRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MajorIncidentService
{
    public function __construct(
        private MajorIncidentRepository $records,
        private TicketRepository $tickets,
        private BillingService $billing,
    ) {
    }

    public function snapshotForTicket(int $ticketId): ?array
    {
        if (! $this->billing->canUseFeature('service_desk')) {
            return null;
        }

        $ticket = $this->tickets->find($ticketId);

        if ($ticket->type !== ServiceCatalogItem::TYPE_INCIDENT) {
            return null;
        }

        $record = $this->records->findForTicket($ticketId);

        return $this->records->snapshot($record);
    }

    public function declare(int $ticketId, int $userId): array
    {
        $this->billing->assertFeature('service_desk');
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

        return $this->records->snapshot($record);
    }

    public function update(int $ticketId, array $data): array
    {
        $this->billing->assertFeature('service_desk');
        $record = $this->assertRecord($ticketId);

        if ($record->status === MajorIncidentRecord::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'ticket' => 'This major incident review is already complete.',
            ]);
        }

        $updated = $this->records->update($record, $data);

        return $this->records->snapshot($updated);
    }

    public function resolve(int $ticketId, int $userId): array
    {
        $this->billing->assertFeature('service_desk');
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

        return $this->records->snapshot($updated);
    }

    public function completeReview(int $ticketId, array $data, int $userId): array
    {
        $this->billing->assertFeature('service_desk');
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

        return $this->records->snapshot($updated);
    }

    public function activeCount(): int
    {
        if (! $this->billing->canUseFeature('service_desk')) {
            return 0;
        }

        return $this->records->activeCount();
    }

    public function pendingReviewCount(): int
    {
        if (! $this->billing->canUseFeature('service_desk')) {
            return 0;
        }

        return $this->records->pendingReviewCount();
    }

    public function index(): array
    {
        $this->billing->assertFeature('service_desk');

        $entries = $this->records->listIndex()
            ->map(fn (MajorIncidentRecord $record) => $this->records->indexEntry($record))
            ->values()
            ->all();

        return [
            'entries' => $entries,
            'active_count' => $this->records->activeCount(),
            'pending_review_count' => $this->records->pendingReviewCount(),
        ];
    }

    public function activeIncidents(): Collection
    {
        $this->billing->assertFeature('service_desk');

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
}
