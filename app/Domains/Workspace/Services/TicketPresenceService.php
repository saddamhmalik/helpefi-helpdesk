<?php

namespace App\Domains\Workspace\Services;

use Illuminate\Support\Facades\Cache;

class TicketPresenceService
{
    private const TTL_SECONDS = 45;

    public function heartbeat(int $ticketId, int $userId, string $name, bool $composing = false): void
    {
        $key = $this->cacheKey($ticketId);
        $presence = Cache::get($key, []);

        $presence[$userId] = [
            'id' => $userId,
            'name' => $name,
            'composing' => $composing,
            'last_seen_at' => now()->toIso8601String(),
        ];

        Cache::put($key, $presence, self::TTL_SECONDS);
    }

    public function leave(int $ticketId, int $userId): void
    {
        $key = $this->cacheKey($ticketId);
        $presence = Cache::get($key, []);

        unset($presence[$userId]);

        if ($presence === []) {
            Cache::forget($key);

            return;
        }

        Cache::put($key, $presence, self::TTL_SECONDS);
    }

    public function viewers(int $ticketId, ?int $exceptUserId = null): array
    {
        $presence = Cache::get($this->cacheKey($ticketId), []);
        $cutoff = now()->subSeconds(self::TTL_SECONDS);

        return collect($presence)
            ->filter(function (array $entry) use ($exceptUserId, $cutoff) {
                if ($exceptUserId && (int) $entry['id'] === $exceptUserId) {
                    return false;
                }

                $seen = isset($entry['last_seen_at'])
                    ? \Illuminate\Support\Carbon::parse($entry['last_seen_at'])
                    : null;

                return $seen === null || $seen->greaterThanOrEqualTo($cutoff);
            })
            ->values()
            ->map(fn (array $entry) => [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'composing' => (bool) ($entry['composing'] ?? false),
            ])
            ->all();
    }

    public function pulse(int $ticketId): void
    {
        Cache::put($this->pulseKey($ticketId), now()->timestamp, 120);
    }

    public function pulseSince(int $ticketId, ?int $sinceTimestamp): bool
    {
        $pulse = Cache::get($this->pulseKey($ticketId));

        if (! $pulse || ! $sinceTimestamp) {
            return false;
        }

        return (int) $pulse > $sinceTimestamp;
    }

    private function cacheKey(int $ticketId): string
    {
        return "ticket_presence:{$ticketId}";
    }

    private function pulseKey(int $ticketId): string
    {
        return "ticket_pulse:{$ticketId}";
    }
}
