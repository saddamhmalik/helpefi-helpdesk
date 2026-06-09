<?php

namespace App\Domains\Reports\Repositories;

use App\Domains\Reports\Models\SavedReport;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

class ReportRepository
{
    public function savedForUser(int $userId): Collection
    {
        return SavedReport::query()
            ->with('schedule')
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();
    }

    public function findSavedForUser(int $id, int $userId): SavedReport
    {
        return SavedReport::query()
            ->where('user_id', $userId)
            ->findOrFail($id);
    }

    public function createSaved(int $userId, string $name, string $type, array $filters, bool $isDefault = false): SavedReport
    {
        if ($isDefault) {
            SavedReport::query()->where('user_id', $userId)->update(['is_default' => false]);
        }

        return SavedReport::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'filters' => $filters,
            'is_default' => $isDefault,
        ]);
    }

    public function deleteSaved(SavedReport $report): void
    {
        $report->delete();
    }

    public function ticketsReport(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->ticketQuery($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function ticketsReportRows(array $filters): Collection
    {
        return $this->ticketQuery($filters)
            ->orderByDesc('created_at')
            ->get();
    }

    public function slaBreachesReport(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->slaBreachesQuery($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function slaBreachesReportRows(array $filters): Collection
    {
        return $this->slaBreachesQuery($filters)
            ->orderByDesc('created_at')
            ->get();
    }

    public function agentPerformanceReport(array $filters): SupportCollection
    {
        $query = Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->whereNotNull('assigned_to');

        $this->applyDateFilters($query, $filters, 'created_at');

        $rows = $query
            ->selectRaw('assigned_to')
            ->selectRaw('SUM(CASE WHEN closed_at IS NULL THEN 1 ELSE 0 END) as open_count')
            ->selectRaw('SUM(CASE WHEN closed_at IS NOT NULL THEN 1 ELSE 0 END) as closed_count')
            ->selectRaw('COUNT(*) as total_count')
            ->groupBy('assigned_to')
            ->get();

        $agents = User::query()
            ->whereIn('id', $rows->pluck('assigned_to'))
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($agents) {
            return [
                'agent_id' => $row->assigned_to,
                'agent_name' => $agents[$row->assigned_to]?->name ?? 'Unknown',
                'open_count' => (int) $row->open_count,
                'closed_count' => (int) $row->closed_count,
                'total_count' => (int) $row->total_count,
            ];
        })->sortByDesc('total_count')->values();
    }

    public function openTicketCount(): int
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->whereHas('status', fn ($q) => $q->where('is_closed', false))
            ->count();
    }

    public function ticketsCreatedSince(Carbon $since): int
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function ticketsResolvedSince(Carbon $since): int
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $since)
            ->count();
    }

    public function activeSlaBreachCount(): int
    {
        return TicketSlaTimer::query()
            ->where(function ($q) {
                $q->where('first_response_breached', true)
                    ->orWhere('resolution_breached', true);
            })
            ->whereHas('ticket', function ($q) {
                $q->whereNull('merged_into_ticket_id')
                    ->whereNull('closed_at');
            })
            ->count();
    }

    public function countByStatus(): Collection
    {
        return TicketStatus::query()
            ->withCount(['tickets' => fn ($q) => $q->whereNull('merged_into_ticket_id')])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color']);
    }

    public function countByPriority(): Collection
    {
        return TicketPriority::query()
            ->withCount(['tickets' => fn ($q) => $q->whereNull('merged_into_ticket_id')->whereNull('closed_at')])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);
    }

    public function topAgentsByOpenTickets(int $limit = 5): SupportCollection
    {
        return Ticket::query()
            ->whereNull('merged_into_ticket_id')
            ->whereNull('closed_at')
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as open_count')
            ->groupBy('assigned_to')
            ->orderByDesc('open_count')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $agent = User::query()->find($row->assigned_to, ['id', 'name']);

                return [
                    'agent_id' => $row->assigned_to,
                    'agent_name' => $agent?->name ?? 'Unknown',
                    'open_count' => (int) $row->open_count,
                ];
            });
    }

    public function ticketVolumeTrend(int $days = 7): array
    {
        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $trend[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'count' => Ticket::query()
                    ->whereNull('merged_into_ticket_id')
                    ->whereDate('created_at', $date)
                    ->count(),
            ];
        }

        return $trend;
    }

    private function ticketQuery(array $filters): Builder
    {
        $query = Ticket::query()
            ->with(['contact:id,name,email', 'status:id,name', 'priority:id,name', 'assignee:id,name'])
            ->whereNull('merged_into_ticket_id');

        $this->applyDateFilters($query, $filters, 'created_at');

        if (! empty($filters['status_id'])) {
            $query->where('ticket_status_id', $filters['status_id']);
        }

        if (! empty($filters['priority_id'])) {
            $query->where('ticket_priority_id', $filters['priority_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query;
    }

    private function slaBreachesQuery(array $filters): Builder
    {
        $query = Ticket::query()
            ->with(['contact:id,name,email', 'status:id,name', 'priority:id,name', 'assignee:id,name', 'slaTimer'])
            ->whereNull('merged_into_ticket_id')
            ->whereHas('slaTimer', function ($q) {
                $q->where('first_response_breached', true)
                    ->orWhere('resolution_breached', true);
            });

        $this->applyDateFilters($query, $filters, 'created_at');

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query;
    }

    private function applyDateFilters(Builder $query, array $filters, string $column): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }
}
