<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Jobs\RecordPlatformSlowQueryJob;
use App\Domains\Platform\Models\PlatformSlowQuery;
use App\Domains\Platform\Repositories\PlatformSlowQueryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformSlowQueryService
{
    public function __construct(private PlatformSlowQueryRepository $queries)
    {
    }

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->queries->paginate($filters, $perPage);
    }

    public function summary(int $days = 7): array
    {
        return $this->queries->summary($days);
    }

    public function show(int $id): PlatformSlowQuery
    {
        return $this->queries->find($id);
    }

    public function deleteByIds(array $ids): int
    {
        return $this->queries->deleteByIds($ids);
    }

    public function deleteMatching(array $filters): int
    {
        return $this->queries->deleteMatching($filters);
    }

    public function record(
        string $connection,
        string $sql,
        float $timeMs,
        ?string $tenantId = null,
        ?array $bindings = null,
        ?string $method = null,
        ?string $url = null,
        ?string $routeName = null,
        ?string $sourceFile = null,
        ?int $sourceLine = null,
        ?string $sourceCallable = null,
        ?string $databaseHost = null,
        ?string $databaseName = null,
    ): void {
        if (! config('database.slow_query.store')) {
            return;
        }

        RecordPlatformSlowQueryJob::dispatch(
            tenantId: $tenantId,
            databaseConnection: $connection,
            sql: $sql,
            timeMs: (int) round($timeMs),
            bindings: $bindings,
            method: $method,
            url: $url,
            routeName: $routeName,
            sourceFile: $sourceFile,
            sourceLine: $sourceLine,
            sourceCallable: $sourceCallable,
            databaseHost: $databaseHost,
            databaseName: $databaseName,
        )
            ->onConnection(config('database.slow_query.queue_connection', config('tenancy.central_queue_connection', 'central')))
            ->onQueue(config('database.slow_query.queue', 'default'));
    }
}
