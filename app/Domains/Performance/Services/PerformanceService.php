<?php

namespace App\Domains\Performance\Services;

use App\Domains\Performance\Repositories\PerformanceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PerformanceService
{
    private const POINTS = [
        'sla_first_response_breach' => -5,
        'sla_resolution_breach' => -10,
        'sla_first_response_met' => 2,
        'sla_resolution_met' => 3,
        'ticket_resolved' => 1,
        'csat_rating_5' => 5,
        'csat_rating_4' => 3,
        'csat_rating_3' => 0,
        'csat_rating_2' => -2,
        'csat_rating_1' => -5,
        'escalation_handled' => 2,
    ];

    public function __construct(private PerformanceRepository $performance)
    {
    }

    public function record(
        int $userId,
        string $eventType,
        ?int $ticketId = null,
        ?int $points = null,
        array $metadata = [],
    ): void {
        $delta = $points ?? (self::POINTS[$eventType] ?? 0);

        if ($delta === 0 && ! array_key_exists($eventType, self::POINTS)) {
            return;
        }

        $this->performance->create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'event_type' => $eventType,
            'points' => $delta,
            'metadata' => $metadata ?: null,
        ]);

        $current = $this->performance->scoreForUser($userId);
        $this->performance->updateScore($userId, $current + $delta);
    }

    public function recordCsat(int $userId, int $ticketId, int $rating): void
    {
        $this->record($userId, "csat_rating_{$rating}", $ticketId, null, [
            'rating' => $rating,
        ]);
    }

    public function history(int $userId, int $perPage = 25): LengthAwarePaginator
    {
        return $this->performance->paginateForUser($userId, $perPage);
    }

    public function summary(int $userId, int $days = 30): array
    {
        return array_merge(
            ['score' => $this->performance->scoreForUser($userId)],
            $this->performance->summaryForUser($userId, $days),
        );
    }

    public static function pointMap(): array
    {
        return self::POINTS;
    }
}
