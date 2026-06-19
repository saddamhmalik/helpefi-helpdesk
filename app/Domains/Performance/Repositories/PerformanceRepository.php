<?php

namespace App\Domains\Performance\Repositories;

use App\Domains\Performance\Models\AgentPerformanceEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

    public function incrementScore(int $userId, float $delta): void
    {
        User::query()->whereKey($userId)->update([
            'performance_score' => DB::raw('GREATEST(0, LEAST(100, ROUND(performance_score + '.((float) $delta).', 2)))'),
        ]);
    }

    public function recentForUser(int $userId, int $limit = 10): Collection
    {
        return AgentPerformanceEvent::query()
            ->with('ticket:id,number,subject')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
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

        $row = AgentPerformanceEvent::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(points), 0) as total_points')
            ->selectRaw('SUM(CASE WHEN points > 0 THEN 1 ELSE 0 END) as positive_events')
            ->selectRaw('SUM(CASE WHEN points < 0 THEN 1 ELSE 0 END) as negative_events')
            ->selectRaw("SUM(CASE WHEN event_type LIKE '%breach%' THEN 1 ELSE 0 END) as violations")
            ->first();

        return [
            'total_points' => (int) ($row->total_points ?? 0),
            'positive_events' => (int) ($row->positive_events ?? 0),
            'negative_events' => (int) ($row->negative_events ?? 0),
            'violations' => (int) ($row->violations ?? 0),
        ];
    }
}
