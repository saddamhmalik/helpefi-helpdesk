<?php

namespace App\Domains\TimeTracking\Repositories;

use App\Domains\TimeTracking\Models\TicketTimeEntry;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class TicketTimeEntryRepository
{
    public function forTicket(int $ticketId): Collection
    {
        return TicketTimeEntry::query()
            ->with(['user:id,name'])
            ->where('ticket_id', $ticketId)
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->get();
    }

    public function snapshotForTicket(int $ticketId): array
    {
        $entries = $this->forTicket($ticketId);

        return [
            'total_minutes' => (int) $entries->sum('minutes'),
            'entries' => $entries->map(fn (TicketTimeEntry $entry) => [
                'id' => $entry->id,
                'minutes' => $entry->minutes,
                'note' => $entry->note,
                'logged_at' => $entry->logged_at?->toIso8601String(),
                'user' => $entry->user ? [
                    'id' => $entry->user->id,
                    'name' => $entry->user->name,
                ] : null,
            ])->values()->all(),
        ];
    }

    public function totalMinutesForTicket(int $ticketId): int
    {
        return (int) TicketTimeEntry::query()
            ->where('ticket_id', $ticketId)
            ->sum('minutes');
    }

    public function findForTicket(int $ticketId, int $entryId): TicketTimeEntry
    {
        return TicketTimeEntry::query()
            ->where('ticket_id', $ticketId)
            ->findOrFail($entryId);
    }

    public function create(array $data): TicketTimeEntry
    {
        return TicketTimeEntry::query()->create($data);
    }

    public function delete(TicketTimeEntry $entry): void
    {
        $entry->delete();
    }

    public function agentRollup(array $filters): SupportCollection
    {
        $query = TicketTimeEntry::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_time_entries.ticket_id')
            ->whereNull('tickets.merged_into_ticket_id');

        $this->applyFilters($query, $filters, 'ticket_time_entries.logged_at');

        $rows = $query
            ->selectRaw('ticket_time_entries.user_id')
            ->selectRaw('SUM(ticket_time_entries.minutes) as total_minutes')
            ->selectRaw('COUNT(*) as entry_count')
            ->groupBy('ticket_time_entries.user_id')
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(fn ($row) => [
            'agent_id' => (int) $row->user_id,
            'agent_name' => $users[$row->user_id]?->name ?? 'Unknown',
            'total_minutes' => (int) $row->total_minutes,
            'entry_count' => (int) $row->entry_count,
        ])->sortByDesc('total_minutes')->values();
    }

    public function teamRollup(array $filters): SupportCollection
    {
        $query = TicketTimeEntry::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_time_entries.ticket_id')
            ->whereNull('tickets.merged_into_ticket_id')
            ->whereNotNull('tickets.team_id');

        $this->applyFilters($query, $filters, 'ticket_time_entries.logged_at');

        $rows = $query
            ->selectRaw('tickets.team_id')
            ->selectRaw('SUM(ticket_time_entries.minutes) as total_minutes')
            ->selectRaw('COUNT(*) as entry_count')
            ->groupBy('tickets.team_id')
            ->get();

        $teams = Team::query()
            ->whereIn('id', $rows->pluck('team_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(fn ($row) => [
            'team_id' => (int) $row->team_id,
            'team_name' => $teams[$row->team_id]?->name ?? 'Unknown',
            'total_minutes' => (int) $row->total_minutes,
            'entry_count' => (int) $row->entry_count,
        ])->sortByDesc('total_minutes')->values();
    }

    private function applyFilters($query, array $filters, string $dateColumn): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('tickets.assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('tickets.team_id', $filters['team_id']);
        }
    }
}
