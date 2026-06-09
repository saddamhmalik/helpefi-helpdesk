<?php

namespace App\Domains\Ai\Repositories;

use App\Domains\Ai\Models\AiDeflectionEvent;
use Illuminate\Support\Carbon;

class AiDeflectionRepository
{
    public function record(array $data): AiDeflectionEvent
    {
        return AiDeflectionEvent::query()->create($data);
    }

    public function summary(array $filters = []): array
    {
        $query = AiDeflectionEvent::query()
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['channel'] ?? null, fn ($q, $channel) => $q->where('channel', $channel));

        $queries = (clone $query)->where('event_type', AiDeflectionEvent::EVENT_QUERY)->count();
        $helpful = (clone $query)->where('event_type', AiDeflectionEvent::EVENT_HELPFUL)->count();
        $notHelpful = (clone $query)->where('event_type', AiDeflectionEvent::EVENT_NOT_HELPFUL)->count();
        $tickets = (clone $query)->where('event_type', AiDeflectionEvent::EVENT_TICKET_CREATED)->count();

        $byChannel = (clone $query)
            ->selectRaw('channel, COUNT(*) as total')
            ->where('event_type', AiDeflectionEvent::EVENT_QUERY)
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->all();

        $feedbackTotal = $helpful + $notHelpful;
        $deflectionRate = $feedbackTotal > 0 ? round(($helpful / $feedbackTotal) * 100, 1) : null;

        return [
            'queries' => $queries,
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'tickets_created' => $tickets,
            'deflection_rate' => $deflectionRate,
            'by_channel' => $byChannel,
        ];
    }

    public function dashboardSummary(): array
    {
        return $this->summary(['date_from' => Carbon::now()->subDays(30)->toDateString()]);
    }
}
