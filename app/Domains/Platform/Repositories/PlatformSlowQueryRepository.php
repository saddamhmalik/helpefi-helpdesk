<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformSlowQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformSlowQueryRepository
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): PlatformSlowQuery
    {
        return PlatformSlowQuery::query()->create($data);
    }

    public function find(int $id): PlatformSlowQuery
    {
        return PlatformSlowQuery::query()
            ->with(['tenant:id,name,slug'])
            ->findOrFail($id);
    }

    public function deleteByIds(array $ids): int
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return 0;
        }

        return PlatformSlowQuery::query()->whereIn('id', $ids)->delete();
    }

    public function deleteMatching(array $filters): int
    {
        return $this->filteredQuery($filters)->delete();
    }

    public function summary(int $days = 7): array
    {
        $since = now()->subDays($days);

        $base = PlatformSlowQuery::query()->where('created_at', '>=', $since);

        return [
            'total' => (clone $base)->count(),
            'avg_time_ms' => (int) round((clone $base)->avg('time_ms') ?? 0),
            'max_time_ms' => (int) ((clone $base)->max('time_ms') ?? 0),
            'tenant_count' => (clone $base)->whereNotNull('tenant_id')->distinct('tenant_id')->count('tenant_id'),
        ];
    }

    private function filteredQuery(array $filters)
    {
        return PlatformSlowQuery::query()
            ->with(['tenant:id,name,slug'])
            ->when($filters['tenant_id'] ?? null, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['connection'] ?? null, fn ($query, $connection) => $query->where('connection', $connection))
            ->when($filters['min_time_ms'] ?? null, fn ($query, $min) => $query->where('time_ms', '>=', (int) $min))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('sql', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%")
                        ->orWhere('tenant_id', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%")
                        ->orWhere('source_file', 'like', "%{$search}%")
                        ->orWhere('source_callable', 'like', "%{$search}%")
                        ->orWhere('database_host', 'like', "%{$search}%")
                        ->orWhere('database_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('time_ms');
    }
}
