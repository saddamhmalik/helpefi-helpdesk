<?php

namespace App\Domains\Security\Support;

use App\Domains\Security\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    public function __construct(private AuditLogService $audit)
    {
    }

    public function record(
        string $event,
        ?Model $subject = null,
        array $properties = [],
        ?int $userId = null,
        ?string $actorEmail = null,
    ): void {
        $actor = auth()->user();

        $this->audit->record(
            $event,
            $userId ?? $actor?->id,
            $actorEmail ?? $actor?->email,
            $subject ? $subject::class : null,
            $subject?->getKey(),
            $properties ?: null,
        );
    }

    public function recordChanges(string $event, Model $subject, array $before, array $after, array $extra = []): void
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

        $this->record($event, $subject, array_merge($extra, ['changes' => $changes]));
    }
}
