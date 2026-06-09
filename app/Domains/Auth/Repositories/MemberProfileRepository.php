<?php

namespace App\Domains\Auth\Repositories;

use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class MemberProfileRepository
{
    public function findEmployee(int $id): User
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', 'customer'))
            ->with([
                'roles:id,name',
                'skills:id,name,slug',
                'teams' => fn ($query) => $query
                    ->select(['teams.id', 'teams.name', 'teams.slug', 'teams.department_id', 'teams.lead_user_id'])
                    ->with('department:id,name,slug,head_user_id,description'),
            ])
            ->findOrFail($id);
    }

    public function assignedTicketStats(int $userId): array
    {
        return $this->aggregateTicketStats(
            Ticket::query()
                ->whereNull('merged_into_ticket_id')
                ->where('assigned_to', $userId),
        );
    }

    public function teamTicketStats(array $teamIds): array
    {
        if ($teamIds === []) {
            return $this->emptyStats();
        }

        return $this->aggregateTicketStats(
            Ticket::query()
                ->whereNull('merged_into_ticket_id')
                ->whereIn('team_id', $teamIds),
        );
    }

    public function departmentTicketStats(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return $this->emptyStats();
        }

        return $this->aggregateTicketStats(
            Ticket::query()
                ->whereNull('merged_into_ticket_id')
                ->whereIn('department_id', $departmentIds),
        );
    }

    public function recentAssignedTickets(int $userId, int $limit = 10): Collection
    {
        return $this->ticketListQuery()
            ->where('assigned_to', $userId)
            ->limit($limit)
            ->get();
    }

    public function recentTeamTickets(array $teamIds, int $limit = 10): Collection
    {
        if ($teamIds === []) {
            return new Collection;
        }

        return $this->ticketListQuery()
            ->whereIn('team_id', $teamIds)
            ->limit($limit)
            ->get();
    }

    public function recentDepartmentTickets(array $departmentIds, int $limit = 10): Collection
    {
        if ($departmentIds === []) {
            return new Collection;
        }

        return $this->ticketListQuery()
            ->whereIn('department_id', $departmentIds)
            ->limit($limit)
            ->get();
    }

    public function assignedTicketsByStatus(int $userId): SupportCollection
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->where('assigned_to', $userId)
            ->join('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.ticket_status_id')
            ->selectRaw('ticket_statuses.name as label, ticket_statuses.slug as slug, ticket_statuses.color as color, COUNT(*) as count')
            ->groupBy('ticket_statuses.id', 'ticket_statuses.name', 'ticket_statuses.slug', 'ticket_statuses.color')
            ->orderBy('ticket_statuses.sort_order')
            ->get();
    }

    public function assignedTicketsByPriority(int $userId): SupportCollection
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->where('assigned_to', $userId)
            ->whereNull('closed_at')
            ->join('ticket_priorities', 'ticket_priorities.id', '=', 'tickets.ticket_priority_id')
            ->selectRaw('ticket_priorities.name as label, ticket_priorities.slug as slug, COUNT(*) as count')
            ->groupBy('ticket_priorities.id', 'ticket_priorities.name', 'ticket_priorities.slug')
            ->orderBy('ticket_priorities.sort_order')
            ->get();
    }

    public function watchingCount(int $userId): int
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->whereHas('watchers', fn ($query) => $query->where('users.id', $userId))
            ->count();
    }

    private function ticketListQuery()
    {
        return Ticket::query()
            ->with([
                'status:id,name,slug,color',
                'priority:id,name,slug',
                'assignee:id,name',
                'contact:id,name,email',
                'department:id,name',
                'team:id,name',
            ])
            ->whereNull('merged_into_ticket_id')
            ->orderByDesc('updated_at');
    }

    private function aggregateTicketStats($query): array
    {
        $row = (clone $query)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN closed_at IS NULL THEN 1 ELSE 0 END) as open_count')
            ->selectRaw('SUM(CASE WHEN closed_at IS NOT NULL THEN 1 ELSE 0 END) as closed_count')
            ->first();

        return [
            'total' => (int) ($row->total_count ?? 0),
            'open' => (int) ($row->open_count ?? 0),
            'closed' => (int) ($row->closed_count ?? 0),
        ];
    }

    private function emptyStats(): array
    {
        return ['total' => 0, 'open' => 0, 'closed' => 0];
    }
}
