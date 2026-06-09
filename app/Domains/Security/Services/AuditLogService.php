<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Jobs\RecordAuditLogJob;
use App\Domains\Security\Repositories\AuditLogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AuditLogService
{
    public function __construct(private AuditLogRepository $logs)
    {
    }

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->logs->paginate($filters, $perPage);
    }

    public function record(
        string $event,
        ?int $userId = null,
        ?string $actorEmail = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
        ?Request $request = null,
    ): void {
        $request ??= request();

        $dispatch = RecordAuditLogJob::dispatch(
            event: $event,
            userId: $userId,
            actorEmail: $actorEmail,
            subjectType: $subjectType,
            subjectId: $subjectId,
            ipAddress: $request?->ip(),
            userAgent: $request?->userAgent(),
            properties: $properties ?: null,
        );

        $connection = config('audit.queue_connection');

        if ($connection) {
            $dispatch->onConnection($connection);
        }

        $dispatch
            ->onQueue(config('audit.queue', 'default'))
            ->afterResponse();
    }

    public function summary(int $days = 7): array
    {
        return $this->logs->recentSummary($days);
    }
}
