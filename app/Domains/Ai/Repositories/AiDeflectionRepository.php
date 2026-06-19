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

        $row = (clone $query)
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as queries', [AiDeflectionEvent::EVENT_QUERY])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as helpful', [AiDeflectionEvent::EVENT_HELPFUL])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as not_helpful', [AiDeflectionEvent::EVENT_NOT_HELPFUL])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as tickets_created', [AiDeflectionEvent::EVENT_TICKET_CREATED])
            ->first();

        $queries = (int) ($row->queries ?? 0);
        $helpful = (int) ($row->helpful ?? 0);
        $notHelpful = (int) ($row->not_helpful ?? 0);
        $tickets = (int) ($row->tickets_created ?? 0);

        $byChannel = $queries > 0
            ? (clone $query)
                ->where('event_type', AiDeflectionEvent::EVENT_QUERY)
                ->selectRaw('channel, COUNT(*) as total')
                ->groupBy('channel')
                ->pluck('total', 'channel')
                ->all()
            : [];

        $feedbackTotal = $helpful + $notHelpful;

        return [
            'queries' => $queries,
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'tickets_created' => $tickets,
            'deflection_rate' => $feedbackTotal > 0 ? round(($helpful / $feedbackTotal) * 100, 1) : null,
            'by_channel' => $byChannel,
        ];
    }

    public function dashboardSummary(): array
    {
        return $this->summary(['date_from' => Carbon::now()->subDays(30)->toDateString()]);
    }
}
