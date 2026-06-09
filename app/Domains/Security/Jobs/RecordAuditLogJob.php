<?php

namespace App\Domains\Security\Jobs;

use App\Domains\Security\Repositories\AuditLogRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordAuditLogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $event,
        public ?int $userId,
        public ?string $actorEmail,
        public ?string $subjectType,
        public ?int $subjectId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?array $properties,
    ) {
    }

    public function handle(AuditLogRepository $logs): void
    {
        $logs->create([
            'user_id' => $this->userId,
            'actor_email' => $this->actorEmail,
            'event' => $this->event,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'properties' => $this->properties,
        ]);
    }
}
