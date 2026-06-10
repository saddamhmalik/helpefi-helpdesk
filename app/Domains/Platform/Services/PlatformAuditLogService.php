<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Jobs\RecordPlatformAuditLogJob;
use App\Domains\Platform\Repositories\PlatformAuditLogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PlatformAuditLogService
{
    public function __construct(private PlatformAuditLogRepository $logs)
    {
    }

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->logs->paginate($filters, $perPage);
    }

    public function record(
        string $event,
        ?int $platformUserId = null,
        ?string $actorEmail = null,
        ?string $tenantId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $properties = [],
        ?Request $request = null,
    ): void {
        $request ??= request();

        $dispatch = RecordPlatformAuditLogJob::dispatch(
            event: $event,
            platformUserId: $platformUserId,
            actorEmail: $actorEmail,
            tenantId: $tenantId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            ipAddress: $request?->ip(),
            userAgent: $request?->userAgent(),
            properties: $properties ?: null,
        );

        $connection = config('platform_audit.queue_connection');

        if ($connection) {
            $dispatch->onConnection($connection);
        }

        $dispatch
            ->onQueue(config('platform_audit.queue', 'default'))
            ->afterResponse();
    }

    public function summary(int $days = 7): array
    {
        return $this->logs->recentSummary($days);
    }
}
