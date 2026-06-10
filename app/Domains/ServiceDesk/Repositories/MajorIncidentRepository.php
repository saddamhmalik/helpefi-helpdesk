<?php

namespace App\Domains\ServiceDesk\Repositories;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\MajorIncidentRecord;
use App\Models\User;
use Illuminate\Support\Collection;

class MajorIncidentRepository
{
    public function findForTicket(int $ticketId): ?MajorIncidentRecord
    {
        return MajorIncidentRecord::query()->where('ticket_id', $ticketId)->first();
    }

    public function create(array $data): MajorIncidentRecord
    {
        return MajorIncidentRecord::query()->create($data);
    }

    public function update(MajorIncidentRecord $record, array $data): MajorIncidentRecord
    {
        $record->fill($data);
        $record->save();

        return $record->fresh();
    }

    public function activeIncidents(int $limit = 50): Collection
    {
        return MajorIncidentRecord::query()
            ->with([
                'ticket.status',
                'ticket.priority',
                'ticket.assignee',
                'declaredBy:id,name,email',
            ])
            ->where('status', MajorIncidentRecord::STATUS_ACTIVE)
            ->whereHas('ticket', fn ($query) => $query->where('type', ServiceCatalogItem::TYPE_INCIDENT))
            ->orderByDesc('declared_at')
            ->limit($limit)
            ->get();
    }

    public function activeCount(): int
    {
        return MajorIncidentRecord::query()
            ->where('status', MajorIncidentRecord::STATUS_ACTIVE)
            ->whereHas('ticket', fn ($query) => $query->where('type', ServiceCatalogItem::TYPE_INCIDENT))
            ->count();
    }

    public function pendingReviewCount(): int
    {
        return MajorIncidentRecord::query()
            ->where('status', MajorIncidentRecord::STATUS_RESOLVED)
            ->count();
    }

    public function listIndex(int $limit = 100): Collection
    {
        return MajorIncidentRecord::query()
            ->with([
                'ticket.status',
                'ticket.priority',
                'ticket.assignee',
                'declaredBy:id,name,email',
                'resolvedBy:id,name,email',
            ])
            ->whereHas('ticket', fn ($query) => $query->where('type', ServiceCatalogItem::TYPE_INCIDENT))
            ->whereIn('status', [
                MajorIncidentRecord::STATUS_ACTIVE,
                MajorIncidentRecord::STATUS_RESOLVED,
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('declared_at')
            ->limit($limit)
            ->get();
    }

    public function snapshot(?MajorIncidentRecord $record): ?array
    {
        if ($record === null) {
            return null;
        }

        $record->loadMissing(['declaredBy:id,name', 'resolvedBy:id,name', 'reviewCompletedBy:id,name', 'ticket.assignee']);

        $coordinatorIds = collect($record->coordinator_user_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $coordinators = $coordinatorIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $coordinatorIds)->get(['id', 'name', 'email']);

        return [
            'id' => $record->id,
            'ticket_id' => $record->ticket_id,
            'status' => $record->status,
            'declared_at' => $record->declared_at?->toIso8601String(),
            'declared_by' => $record->declaredBy?->name,
            'resolved_at' => $record->resolved_at?->toIso8601String(),
            'resolved_by' => $record->resolvedBy?->name,
            'coordinator_user_ids' => $coordinatorIds->all(),
            'coordinators' => $coordinators->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all(),
            'war_room_notes' => $record->war_room_notes,
            'summary' => $record->summary,
            'timeline' => $record->timeline,
            'lessons_learned' => $record->lessons_learned,
            'action_items' => $record->action_items,
            'review_completed_at' => $record->review_completed_at?->toIso8601String(),
            'review_completed_by' => $record->reviewCompletedBy?->name,
            'war_room_url' => '/service-desk/major-incidents/'.$record->ticket_id.'/war-room',
        ];
    }

    public function indexEntry(MajorIncidentRecord $record): array
    {
        $ticket = $record->ticket;

        return [
            'id' => $record->id,
            'ticket_id' => $record->ticket_id,
            'number' => $ticket?->number,
            'subject' => $ticket?->subject,
            'status' => $record->status,
            'ticket_status' => $ticket?->status?->name,
            'priority' => $ticket?->priority?->name,
            'assignee' => $ticket?->assignee?->name,
            'declared_at' => $record->declared_at?->toIso8601String(),
            'declared_by' => $record->declaredBy?->name,
            'resolved_at' => $record->resolved_at?->toIso8601String(),
            'war_room_url' => '/service-desk/major-incidents/'.$record->ticket_id.'/war-room',
        ];
    }
}
