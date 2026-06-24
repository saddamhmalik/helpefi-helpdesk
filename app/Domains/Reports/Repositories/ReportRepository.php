<?php

namespace App\Domains\Reports\Repositories;

use App\Domains\Reports\Models\SavedReport;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketStatusLookup;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    public function __construct(private TicketStatusLookup $statusLookup)
    {
    }

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

    public function ticketsReportRows(array $filters): LazyCollection
    {
        return $this->ticketQuery($filters)
            ->orderByDesc('created_at')
            ->cursor();
    }

    public function slaBreachesReport(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->slaBreachesQuery($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function slaBreachesReportRows(array $filters): LazyCollection
    {
        return $this->slaBreachesQuery($filters)
            ->orderByDesc('created_at')
            ->cursor();
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
        return (int) ($this->dashboardTicketCounts(now()->startOfWeek())['open_tickets'] ?? 0);
    }

    public function ticketsCreatedSince(Carbon $since): int
    {
        return (int) ($this->dashboardTicketCounts($since)['created_since'] ?? 0);
    }

    public function ticketsResolvedSince(Carbon $since): int
    {
        return (int) ($this->dashboardTicketCounts($since)['resolved_since'] ?? 0);
    }

    public function dashboardSnapshot(Carbon $weekStart, int $trendDays = 7): array
    {
        $rollup = $this->dashboardTicketRollup($weekStart, $trendDays);
        $distribution = $this->ticketDistributionCounts();

        return array_merge($rollup, [
            'ticket_statuses' => $this->hydrateStatusCounts($distribution['status']),
            'ticket_priorities' => $this->hydratePriorityCounts($distribution['priority']),
            'top_agents' => $this->topAgentsByOpenTickets(),
            'sla_breaches' => $this->activeSlaBreachCount(),
        ]);
    }

    public function dashboardMetaCounts(): array
    {
        $row = DB::selectOne('
            SELECT
                (SELECT COUNT(*) FROM contacts) AS contacts,
                (SELECT COUNT(*) FROM knowledge_articles WHERE is_published = 1) AS published_articles
        ');

        return [
            'contacts' => (int) ($row->contacts ?? 0),
            'published_articles' => (int) ($row->published_articles ?? 0),
        ];
    }

    public function dashboardTicketCounts(Carbon $since): array
    {
        $rollup = $this->dashboardTicketRollup($since, 0);

        return [
            'open_tickets' => $rollup['open_tickets'],
            'created_since' => $rollup['created_since'],
            'resolved_since' => $rollup['resolved_since'],
        ];
    }

    private function dashboardTicketRollup(Carbon $weekStart, int $trendDays = 7): array
    {
        $selects = [
            $this->statusLookup->sumOpenTicketsSelect('tickets.ticket_status_id', 'open_tickets'),
            'SUM(CASE WHEN tickets.created_at >= ? THEN 1 ELSE 0 END) as created_since',
            'SUM(CASE WHEN tickets.closed_at IS NOT NULL AND tickets.closed_at >= ? THEN 1 ELSE 0 END) as resolved_since',
        ];
        $bindings = [$weekStart, $weekStart];

        for ($i = $trendDays - 1; $i >= 0; $i--) {
            $selects[] = 'SUM(CASE WHEN DATE(tickets.created_at) = ? THEN 1 ELSE 0 END) as trend_'.$i;
            $bindings[] = now()->subDays($i)->startOfDay()->toDateString();
        }

        $row = Ticket::query()
            ->whereNull('tickets.merged_into_ticket_id')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        $volumeTrend = [];

        if ($trendDays > 0) {
            for ($i = $trendDays - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->startOfDay();
                $volumeTrend[] = [
                    'date' => $date->toDateString(),
                    'label' => $date->format('D'),
                    'count' => (int) ($row->{'trend_'.$i} ?? 0),
                ];
            }
        }

        return [
            'open_tickets' => (int) ($row->open_tickets ?? 0),
            'created_since' => (int) ($row->created_since ?? 0),
            'resolved_since' => (int) ($row->resolved_since ?? 0),
            'volume_trend' => $volumeTrend,
        ];
    }

    private function ticketDistributionCounts(): array
    {
        $rows = DB::select("
            SELECT 'status' AS kind, ticket_status_id AS ref_id, COUNT(*) AS aggregate
            FROM tickets
            WHERE merged_into_ticket_id IS NULL
            GROUP BY ticket_status_id
            UNION ALL
            SELECT 'priority', ticket_priority_id, COUNT(*)
            FROM tickets
            WHERE merged_into_ticket_id IS NULL AND closed_at IS NULL
            GROUP BY ticket_priority_id
        ");

        $status = [];
        $priority = [];

        foreach ($rows as $row) {
            if ($row->ref_id === null) {
                continue;
            }

            if ($row->kind === 'status') {
                $status[(int) $row->ref_id] = (int) $row->aggregate;
            } else {
                $priority[(int) $row->ref_id] = (int) $row->aggregate;
            }
        }

        return [
            'status' => $status,
            'priority' => $priority,
        ];
    }

    private function hydrateStatusCounts(array $counts): Collection
    {
        return TicketStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color'])
            ->each(fn (TicketStatus $status) => $status->setAttribute(
                'tickets_count',
                (int) ($counts[$status->id] ?? 0),
            ));
    }

    private function hydratePriorityCounts(array $counts): Collection
    {
        return TicketPriority::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug'])
            ->each(fn (TicketPriority $priority) => $priority->setAttribute(
                'tickets_count',
                (int) ($counts[$priority->id] ?? 0),
            ));
    }

    public function activeSlaBreachCount(): int
    {
        return TicketSlaTimer::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_sla_timers.ticket_id')
            ->whereNull('tickets.merged_into_ticket_id')
            ->whereNull('tickets.closed_at')
            ->where(function ($query) {
                $query->where('ticket_sla_timers.first_response_breached', true)
                    ->orWhere('ticket_sla_timers.resolution_breached', true);
            })
            ->count();
    }

    public function countByStatus(): Collection
    {
        return $this->hydrateStatusCounts($this->ticketDistributionCounts()['status']);
    }

    public function countByPriority(): Collection
    {
        return $this->hydratePriorityCounts($this->ticketDistributionCounts()['priority']);
    }

    public function topAgentsByOpenTickets(int $limit = 5): SupportCollection
    {
        return Ticket::query()
            ->whereNull('tickets.merged_into_ticket_id')
            ->whereNull('tickets.closed_at')
            ->whereNotNull('tickets.assigned_to')
            ->join('users', 'users.id', '=', 'tickets.assigned_to')
            ->selectRaw('users.id as agent_id, users.name as agent_name, COUNT(*) as open_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('open_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'agent_id' => $row->agent_id,
                'agent_name' => $row->agent_name,
                'open_count' => (int) $row->open_count,
            ]);
    }

    public function ticketVolumeTrend(int $days = 7): array
    {
        return $this->dashboardTicketRollup(now()->startOfWeek(), $days)['volume_trend'];
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
