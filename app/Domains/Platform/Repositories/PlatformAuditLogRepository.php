<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformAuditLogRepository
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

    public function create(array $data): PlatformAuditLog
    {
        return PlatformAuditLog::query()->create($data);
    }

    public function recentSummary(int $days = 7): array
    {
        $since = now()->subDays($days);

        return PlatformAuditLog::query()
            ->selectRaw('event, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('event')
            ->orderByDesc('total')
            ->pluck('total', 'event')
            ->all();
    }

    private function filteredQuery(array $filters)
    {
        return PlatformAuditLog::query()
            ->with(['user:id,name,email', 'tenant:id,name,slug'])
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['tenant_id'] ?? null, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('actor_email', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('tenant_id', 'like', "%{$search}%");

                    $inner->orWhere('subject_id', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }
}
