<?php

namespace App\Domains\Platform\Support;

use App\Domains\Platform\Services\PlatformAuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PlatformAuditRecorder
{
    public function __construct(private PlatformAuditLogService $audit)
    {
    }

    public function record(
        string $event,
        ?Model $subject = null,
        array $properties = [],
        ?int $platformUserId = null,
        ?string $actorEmail = null,
        ?string $tenantId = null,
    ): void {
        $actor = Auth::guard('platform')->user();

        $this->audit->record(
            $event,
            $platformUserId ?? $actor?->id,
            $actorEmail ?? $actor?->email,
            $tenantId,
            $subject ? $subject::class : null,
            $subject?->getKey() !== null ? (string) $subject->getKey() : null,
            $properties,
        );
    }

    public function recordChanges(string $event, Model $subject, array $before, array $after, array $extra = [], ?string $tenantId = null): void
    {
        $changes = [];

        foreach ($after as $key => $value) {
            if (array_key_exists($key, $before) && $before[$key] != $value) {
                $changes[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }

        if ($changes === []) {
            return;
        }

        $this->record($event, $subject, array_merge($extra, ['changes' => $changes]), tenantId: $tenantId);
    }
}
