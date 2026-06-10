<?php

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Concerns\RunsOnCentralQueue;
use App\Domains\Platform\Repositories\PlatformAuditLogRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPlatformAuditLogJob implements ShouldQueue
{
    use Queueable;
    use RunsOnCentralQueue;

    public int $tries = 3;

    public function __construct(
        public string $event,
        public ?int $platformUserId,
        public ?string $actorEmail,
        public ?string $tenantId,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?array $properties,
    ) {
        $this->bindToCentralQueue();
    }

    public function handle(PlatformAuditLogRepository $logs): void
    {
        $this->ensureCentralContext();

        $logs->create([
            'platform_user_id' => $this->platformUserId,
            'actor_email' => $this->actorEmail,
            'tenant_id' => $this->tenantId,
            'event' => $this->event,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'properties' => $this->properties,
        ]);
    }
}
