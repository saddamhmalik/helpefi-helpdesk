<?php

namespace App\Domains\Performance\Repositories;

use App\Domains\Performance\Models\AgentPerformanceEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PerformanceRepository
{
    public function create(array $data): AgentPerformanceEvent
    {
        return AgentPerformanceEvent::query()->create($data);
    }

    public function scoreForUser(int $userId): float
    {
        return (float) User::query()->whereKey($userId)->value('performance_score');
    }

    public function updateScore(int $userId, float $score): void
    {
        User::query()->whereKey($userId)->update([
            'performance_score' => max(0, min(100, round($score, 2))),
        ]);
    }

    public function paginateForUser(int $userId, int $perPage = 25): LengthAwarePaginator
    {
        return AgentPerformanceEvent::query()
            ->with('ticket:id,number,subject')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function summaryForUser(int $userId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $events = AgentPerformanceEvent::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->get(['event_type', 'points']);

        return [
            'total_points' => (int) $events->sum('points'),
            'positive_events' => $events->where('points', '>', 0)->count(),
            'negative_events' => $events->where('points', '<', 0)->count(),
            'violations' => $events->filter(fn ($event) => str_contains($event->event_type, 'breach'))->count(),
        ];
    }
}
