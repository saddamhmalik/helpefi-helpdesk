<?php

namespace App\Domains\Security\Repositories;

use App\Domains\Security\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogRepository
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function exportRows(array $filters, callable $callback): void
    {
        $this->filteredQuery($filters)
            ->chunkById(500, function ($logs) use ($callback) {
                foreach ($logs as $log) {
                    $callback($log);
                }
            });
    }

    private function filteredQuery(array $filters)
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('actor_email', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%");

                    if (is_numeric($search)) {
                        $inner->orWhere('subject_id', (int) $search);
                    }
                });
            })
            ->orderByDesc('created_at');
    }

    public function create(array $data): AuditLog
    {
        return AuditLog::query()->create($data);
    }

    public function deleteOlderThan(int $days): int
    {
        return AuditLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    public function recentSummary(int $days = 7): array
    {
        $since = now()->subDays($days);

        return AuditLog::query()
            ->selectRaw('event, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('event')
            ->orderByDesc('total')
            ->pluck('total', 'event')
            ->all();
    }

    public function forTicket(int $ticketId, array $excludeEvents = []): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($query) use ($ticketId) {
                $query->where(function ($inner) use ($ticketId) {
                    $inner->where('subject_type', \App\Domains\Tickets\Models\Ticket::class)
                        ->where('subject_id', $ticketId);
                })->orWhere(function ($inner) use ($ticketId) {
                    $inner->where('subject_type', \App\Domains\SideConversations\Models\SideConversation::class)
                        ->where('properties->ticket_id', $ticketId);
                });
            })
            ->when($excludeEvents !== [], fn ($query) => $query->whereNotIn('event', $excludeEvents))
            ->orderBy('created_at')
            ->get();
    }
}
