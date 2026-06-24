<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Models\TicketStatus;
use App\Support\TenantCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TicketStatusLookup
{
    public function firstClosedId(): ?int
    {
        return $this->rememberScalar('ticket_status.first_closed_id', fn () => $this->queryFirstClosedId());
    }

    public function firstOpenId(): ?int
    {
        return $this->rememberScalar('ticket_status.first_open_id', fn () => $this->queryFirstOpenId());
    }

    public function closedIds(): Collection
    {
        return $this->rememberIdCollection('ticket_status.closed_ids', fn () => $this->queryClosedIds());
    }

    public function openIds(): Collection
    {
        return $this->rememberIdCollection('ticket_status.open_ids', fn () => $this->queryOpenIds());
    }

    public function isClosedId(int $statusId): bool
    {
        return $this->closedIds()->contains($statusId);
    }

    public function restrictToOpenTickets(Builder $query, string $column = 'ticket_status_id'): void
    {
        $openIds = $this->openIds();

        if ($openIds->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($column, $openIds);
    }

    public function restrictToOpenStatusRelation(Builder $query, string $relation = 'status'): void
    {
        $openIds = $this->openIds();

        if ($openIds->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas($relation, fn (Builder $statusQuery) => $statusQuery->whereIn('id', $openIds));
    }

    public function sumOpenTicketsSelect(string $column = 'tickets.ticket_status_id', string $alias = 'open_tickets'): string
    {
        $list = $this->openIdsListSql();

        if ($list === '0') {
            return "0 as {$alias}";
        }

        return "SUM(CASE WHEN {$column} IN ({$list}) THEN 1 ELSE 0 END) as {$alias}";
    }

    public function sumOpenUnassignedTicketsSelect(
        string $statusColumn = 'tickets.ticket_status_id',
        string $alias = 'unassigned_count',
    ): string {
        $list = $this->openIdsListSql();

        if ($list === '0') {
            return "0 as {$alias}";
        }

        return "SUM(CASE WHEN {$statusColumn} IN ({$list}) AND tickets.assigned_to IS NULL THEN 1 ELSE 0 END) as {$alias}";
    }

    public function defaultClosed(): TicketStatus
    {
        if (! tenancy()->initialized) {
            return $this->queryDefaultClosed();
        }

        $id = Cache::remember(
            TenantCache::key('ticket_status.default_closed'),
            3600,
            fn () => $this->queryDefaultClosed()->id,
        );

        return TicketStatus::query()->findOrFail($id);
    }

    public function defaultOpen(): ?TicketStatus
    {
        return $this->rememberStatus('ticket_status.default_open', fn () => $this->queryDefaultOpen());
    }

    public function firstClosed(): ?TicketStatus
    {
        return $this->rememberStatus('ticket_status.first_closed', fn () => $this->queryFirstClosed());
    }

    public static function forget(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        foreach ([
            'ticket_status.first_closed_id',
            'ticket_status.first_open_id',
            'ticket_status.closed_ids',
            'ticket_status.open_ids',
            'ticket_status.default_closed',
            'ticket_status.default_open',
            'ticket_status.first_closed',
        ] as $key) {
            Cache::forget(TenantCache::key($key));
        }
    }

    private function rememberScalar(string $key, callable $callback): mixed
    {
        if (! tenancy()->initialized) {
            return $callback();
        }

        return Cache::remember(TenantCache::key($key), 3600, $callback);
    }

    private function rememberIdCollection(string $key, callable $callback): Collection
    {
        if (! tenancy()->initialized) {
            return $this->normalizeIds($callback());
        }

        $ids = Cache::remember(
            TenantCache::key($key),
            3600,
            fn () => $this->normalizeIds($callback())->all(),
        );

        return $this->normalizeIds(collect($ids));
    }

    private function rememberStatus(string $key, callable $callback): ?TicketStatus
    {
        if (! tenancy()->initialized) {
            return $callback();
        }

        $id = Cache::remember(
            TenantCache::key($key),
            3600,
            fn () => $callback()?->id,
        );

        if ($id === null) {
            return null;
        }

        return TicketStatus::query()->find($id);
    }

    private function queryFirstClosed(): ?TicketStatus
    {
        return TicketStatus::query()
            ->where('is_closed', true)
            ->orderBy('sort_order')
            ->first();
    }

    private function queryFirstClosedId(): ?int
    {
        return TicketStatus::query()
            ->where('is_closed', true)
            ->orderBy('sort_order')
            ->value('id');
    }

    private function queryFirstOpenId(): ?int
    {
        return TicketStatus::query()
            ->where('is_closed', false)
            ->orderBy('sort_order')
            ->value('id');
    }

    private function queryClosedIds(): Collection
    {
        return TicketStatus::query()
            ->where('is_closed', true)
            ->orderBy('sort_order')
            ->pluck('id');
    }

    private function queryOpenIds(): Collection
    {
        return TicketStatus::query()
            ->where('is_closed', false)
            ->orderBy('sort_order')
            ->pluck('id');
    }

    private function openIdsListSql(): string
    {
        $openIds = $this->normalizeIds($this->openIds());

        return $openIds->isEmpty() ? '0' : $openIds->implode(',');
    }

    private function normalizeIds(Collection $ids): Collection
    {
        return $ids
            ->map(function (mixed $id): ?int {
                if (is_array($id)) {
                    $id = $id['id'] ?? null;
                }

                if ($id === null || $id === '') {
                    return null;
                }

                return (int) $id;
            })
            ->filter(fn (?int $id) => $id !== null && $id > 0)
            ->values();
    }

    private function queryDefaultClosed(): TicketStatus
    {
        return TicketStatus::query()
            ->where('slug', 'closed')
            ->first()
            ?? TicketStatus::query()
                ->where('is_closed', true)
                ->orderBy('sort_order')
                ->firstOrFail();
    }

    private function queryDefaultOpen(): ?TicketStatus
    {
        return TicketStatus::query()
            ->where('slug', 'open')
            ->first()
            ?? TicketStatus::query()
                ->where('is_closed', false)
                ->orderBy('sort_order')
                ->first();
    }
}
