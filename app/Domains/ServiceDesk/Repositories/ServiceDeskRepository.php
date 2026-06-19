<?php

namespace App\Domains\ServiceDesk\Repositories;

use App\Domains\ServiceDesk\Support\TicketTypes;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ServiceDeskRepository
{
    public function typeSummaries(): array
    {
        $types = TicketTypes::all();
        $typeValues = TicketTypes::values();

        $rows = $this->baseQuery()
            ->leftJoin('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.ticket_status_id')
            ->whereIn('tickets.type', $typeValues)
            ->select('tickets.type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN ticket_statuses.is_closed = 0 THEN 1 ELSE 0 END) as open_count')
            ->selectRaw('SUM(CASE WHEN ticket_statuses.is_closed = 0 AND tickets.assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned_count')
            ->groupBy('tickets.type')
            ->get()
            ->keyBy('type');

        return collect($types)
            ->map(function (array $type) use ($rows) {
                $row = $rows->get($type['value']);

                return [
                    'type' => $type['value'],
                    'label' => $type['label'],
                    'singular' => $type['singular'],
                    'description' => $type['description'],
                    'total' => (int) ($row->total ?? 0),
                    'open' => (int) ($row->open_count ?? 0),
                    'unassigned' => (int) ($row->unassigned_count ?? 0),
                ];
            })
            ->all();
    }

    public function recentByType(string $type, int $limit = 5): Collection
    {
        return $this->recentGroupedByType($limit)->get($type, collect());
    }

    public function recentGroupedByType(int $limitPerType = 5): SupportCollection
    {
        $types = TicketTypes::values();
        $rankedQuery = $this->baseQuery()
            ->select('tickets.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY tickets.type ORDER BY tickets.updated_at DESC) as row_num')
            ->whereIn('tickets.type', $types);

        return Ticket::query()
            ->fromSub($rankedQuery, 'ranked_tickets')
            ->where('row_num', '<=', $limitPerType)
            ->with([
                'contact:id,name,email',
                'status:id,name,slug,color',
                'priority:id,name,slug',
                'assignee' => fn ($query) => $query
                    ->select(['id', 'name', 'email'])
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'agent'])),
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('type');
    }

    private function baseQuery()
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->visibleInQueue();
    }
}
