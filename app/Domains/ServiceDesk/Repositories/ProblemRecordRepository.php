<?php

namespace App\Domains\ServiceDesk\Repositories;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ProblemIncidentLink;
use App\Domains\ServiceDesk\Models\ProblemRecord;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Collection;

class ProblemRecordRepository
{
    public function findForTicket(int $ticketId): ?ProblemRecord
    {
        return ProblemRecord::query()->where('ticket_id', $ticketId)->first();
    }

    public function findOrCreateForTicket(int $ticketId): ProblemRecord
    {
        return ProblemRecord::query()->firstOrCreate(['ticket_id' => $ticketId]);
    }

    public function update(ProblemRecord $record, array $data): ProblemRecord
    {
        $record->fill($data);
        $record->save();

        return $record->fresh();
    }

    public function linkedIncidents(int $problemTicketId): Collection
    {
        return ProblemIncidentLink::query()
            ->with(['incidentTicket.status', 'incidentTicket.contact'])
            ->where('problem_ticket_id', $problemTicketId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function linkExists(int $problemTicketId, int $incidentTicketId): bool
    {
        return ProblemIncidentLink::query()
            ->where('problem_ticket_id', $problemTicketId)
            ->where('incident_ticket_id', $incidentTicketId)
            ->exists();
    }

    public function createLink(int $problemTicketId, int $incidentTicketId, ?int $userId): ProblemIncidentLink
    {
        return ProblemIncidentLink::query()->create([
            'problem_ticket_id' => $problemTicketId,
            'incident_ticket_id' => $incidentTicketId,
            'linked_by_user_id' => $userId,
        ]);
    }

    public function deleteLink(int $problemTicketId, int $incidentTicketId): bool
    {
        return ProblemIncidentLink::query()
            ->where('problem_ticket_id', $problemTicketId)
            ->where('incident_ticket_id', $incidentTicketId)
            ->delete() > 0;
    }

    public function incidentCandidates(int $problemTicketId, int $limit = 50): Collection
    {
        $linkedIds = ProblemIncidentLink::query()
            ->where('problem_ticket_id', $problemTicketId)
            ->pluck('incident_ticket_id');

        return Ticket::query()
            ->with(['status', 'contact'])
            ->where('type', ServiceCatalogItem::TYPE_INCIDENT)
            ->where('id', '!=', $problemTicketId)
            ->when($linkedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedIds))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function snapshot(?ProblemRecord $record, int $problemTicketId): ?array
    {
        if ($record === null) {
            return null;
        }

        $incidents = $this->linkedIncidents($problemTicketId)->map(fn (ProblemIncidentLink $link) => [
            'id' => $link->incident_ticket_id,
            'number' => $link->incidentTicket?->number,
            'subject' => $link->incidentTicket?->subject,
            'status' => $link->incidentTicket?->status?->name,
            'contact' => $link->incidentTicket?->contact?->name,
            'linked_at' => $link->created_at?->toIso8601String(),
        ])->values()->all();

        return [
            'id' => $record->id,
            'ticket_id' => $record->ticket_id,
            'root_cause' => $record->root_cause,
            'workaround' => $record->workaround,
            'is_known_error' => $record->is_known_error,
            'incidents' => $incidents,
        ];
    }
}
