<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Knowledge\Models\KbDeflectionEvent;
use Illuminate\Support\Carbon;

class KbDeflectionRepository
{
    public function record(array $data): KbDeflectionEvent
    {
        return KbDeflectionEvent::query()->create($data);
    }

    public function summary(array $filters = []): array
    {
        $query = KbDeflectionEvent::query()
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));

        $row = (clone $query)
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as suggestions_shown', [KbDeflectionEvent::EVENT_SUGGESTIONS_SHOWN])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as deflected', [KbDeflectionEvent::EVENT_DEFLECTED])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as continued', [KbDeflectionEvent::EVENT_CONTINUED])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as tickets_created', [KbDeflectionEvent::EVENT_TICKET_CREATED])
            ->selectRaw('SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as article_clicks', [KbDeflectionEvent::EVENT_ARTICLE_CLICKED])
            ->first();

        $suggestionsShown = (int) ($row->suggestions_shown ?? 0);
        $deflected = (int) ($row->deflected ?? 0);
        $continued = (int) ($row->continued ?? 0);
        $tickets = (int) ($row->tickets_created ?? 0);
        $articleClicks = (int) ($row->article_clicks ?? 0);

        $outcomes = $deflected + $continued + $tickets;

        return [
            'suggestions_shown' => $suggestionsShown,
            'deflected' => $deflected,
            'continued' => $continued,
            'tickets_created' => $tickets,
            'article_clicks' => $articleClicks,
            'deflection_rate' => $outcomes > 0 ? round(($deflected / $outcomes) * 100, 1) : null,
        ];
    }

    public function dashboardSummary(): array
    {
        return $this->summary(['date_from' => Carbon::now()->subDays(30)->toDateString()]);
    }
}
