<?php

namespace App\Domains\ServiceDesk\Repositories;

use App\Domains\ServiceDesk\Support\TicketTypes;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class ServiceDeskRepository
{
    public function typeSummaries(): array
    {
        return collect(TicketTypes::all())
            ->map(function (array $type) {
                $query = $this->baseQuery()->where('type', $type['value']);

                return [
                    'type' => $type['value'],
                    'label' => $type['label'],
                    'singular' => $type['singular'],
                    'description' => $type['description'],
                    'total' => (clone $query)->count(),
                    'open' => (clone $query)->whereHas('status', fn ($status) => $status->where('is_closed', false))->count(),
                    'unassigned' => (clone $query)
                        ->whereNull('assigned_to')
                        ->whereHas('status', fn ($status) => $status->where('is_closed', false))
                        ->count(),
                ];
            })
            ->all();
    }

    public function recentByType(string $type, int $limit = 5): Collection
    {
        return $this->baseQuery()
            ->where('type', $type)
            ->with([
                'contact:id,name,email',
                'status:id,name,slug,color',
                'priority:id,name,slug',
                'assignee' => fn ($query) => $query
                    ->select(['id', 'name', 'email'])
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'agent'])),
            ])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    private function baseQuery()
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->visibleInQueue();
    }
}
