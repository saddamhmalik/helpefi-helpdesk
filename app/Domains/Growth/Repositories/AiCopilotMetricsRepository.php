<?php

namespace App\Domains\Growth\Repositories;

use App\Domains\Ai\Models\AiCopilotMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AiCopilotMetricsRepository
{
    public function summary(array $filters = []): array
    {
        $query = AiCopilotMessage::query()
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));

        $userMessages = (clone $query)->where('role', AiCopilotMessage::ROLE_USER)->count();
        $assistantMessages = (clone $query)->where('role', AiCopilotMessage::ROLE_ASSISTANT)->count();
        $uniqueAgents = (clone $query)->distinct('user_id')->count('user_id');
        $uniqueTickets = (clone $query)->distinct('ticket_id')->count('ticket_id');

        $topAgents = (clone $query)
            ->where('role', AiCopilotMessage::ROLE_USER)
            ->select('user_id', DB::raw('COUNT(*) as messages'))
            ->groupBy('user_id')
            ->orderByDesc('messages')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $user = \App\Models\User::query()->find($row->user_id);

                return [
                    'user_id' => $row->user_id,
                    'name' => $user?->name ?? 'Unknown',
                    'messages' => (int) $row->messages,
                ];
            })
            ->values()
            ->all();

        $trend = $this->dailyTrend($filters);

        return [
            'user_messages' => $userMessages,
            'assistant_messages' => $assistantMessages,
            'unique_agents' => $uniqueAgents,
            'unique_tickets' => $uniqueTickets,
            'top_agents' => $topAgents,
            'trend' => $trend,
        ];
    }

    public function dashboardSummary(): array
    {
        return $this->summary(['date_from' => Carbon::now()->subDays(30)->toDateString()]);
    }

    private function dailyTrend(array $filters): array
    {
        $from = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();
        $to = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $rows = AiCopilotMessage::query()
            ->where('role', AiCopilotMessage::ROLE_USER)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->all();

        $trend = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $trend[] = [
                'date' => $key,
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $trend;
    }
}
