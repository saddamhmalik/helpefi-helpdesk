<?php

namespace App\Domains\Csat\Repositories;

use App\Domains\Csat\Models\CsatResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CsatResponseRepository
{
    public function findForTicket(int $ticketId): ?CsatResponse
    {
        return CsatResponse::query()->where('ticket_id', $ticketId)->first();
    }

    public function create(array $data): CsatResponse
    {
        return CsatResponse::query()->create($data);
    }

    public function paginateReport(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return CsatResponse::query()
            ->with(['ticket:id,number,subject,assigned_to', 'ticket.assignee:id,name', 'contact:id,name,email'])
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function reportRows(array $filters): Collection
    {
        return CsatResponse::query()
            ->with(['ticket:id,number,subject,assigned_to', 'ticket.assignee:id,name', 'contact:id,name,email'])
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')
            ->get();
    }

    public function summary(array $filters = []): array
    {
        $query = CsatResponse::query()
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));

        $total = (clone $query)->count();
        $average = $total > 0 ? round((clone $query)->avg('rating'), 2) : null;

        $breakdown = (clone $query)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('total', 'rating')
            ->all();

        $byChannel = (clone $query)
            ->selectRaw('channel, COUNT(*) as total')
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->all();

        return [
            'total_responses' => $total,
            'average_rating' => $average,
            'breakdown' => $breakdown,
            'by_channel' => $byChannel,
        ];
    }
}
