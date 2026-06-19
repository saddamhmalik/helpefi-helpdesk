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

        $row = (clone $query)
            ->selectRaw('COUNT(*) as total_responses')
            ->selectRaw('AVG(rating) as average_rating')
            ->selectRaw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1')
            ->selectRaw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2')
            ->selectRaw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3')
            ->selectRaw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4')
            ->selectRaw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5')
            ->first();

        $total = (int) ($row->total_responses ?? 0);

        $breakdown = $total > 0
            ? collect(range(1, 5))
                ->mapWithKeys(fn (int $rating) => [$rating => (int) ($row->{'rating_'.$rating} ?? 0)])
                ->filter(fn (int $count) => $count > 0)
                ->all()
            : [];

        $byChannel = $total > 0
            ? (clone $query)
                ->selectRaw('channel, COUNT(*) as total')
                ->groupBy('channel')
                ->pluck('total', 'channel')
                ->all()
            : [];

        return [
            'total_responses' => $total,
            'average_rating' => $total > 0 ? round((float) $row->average_rating, 2) : null,
            'breakdown' => $breakdown,
            'by_channel' => $byChannel,
        ];
    }
}
