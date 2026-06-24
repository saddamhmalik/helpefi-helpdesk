<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Repositories\TicketReadRepository;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

class TicketReadService
{
    public function __construct(private TicketReadRepository $reads)
    {
    }

    public function countForTicket(int $userId, int $ticketId): int
    {
        return $this->reads->countForTicket($userId, $ticketId);
    }

    public function countsForTickets(int $userId, array $ticketIds): array
    {
        return $this->reads->countsForTickets($userId, $ticketIds);
    }

    public function markAsRead(int $userId, int $ticketId, ?int $messageId = null): void
    {
        $this->reads->markAsRead($userId, $ticketId, $messageId);
    }

    public function incrementUnreadForCustomerMessage(int $ticketId): void
    {
        $this->reads->incrementUnreadForCustomerMessage($ticketId);
    }

    public function attachUnreadCounts(Paginator $paginator, int $userId): Paginator
    {
        $ticketIds = $paginator->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $counts = $this->countsForTickets($userId, $ticketIds);

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($ticket) use ($counts) {
                $ticket->setAttribute('unread_count', $counts[$ticket->id] ?? 0);

                return $ticket;
            }),
        );

        return $paginator;
    }

    public function attachUnreadCountsToCollection(Collection $tickets, int $userId): Collection
    {
        $ticketIds = $tickets->pluck('id')->map(fn ($id) => (int) $id)->all();
        $counts = $this->countsForTickets($userId, $ticketIds);

        return $tickets->map(function ($ticket) use ($counts) {
            $ticket->setAttribute('unread_count', $counts[$ticket->id] ?? 0);

            return $ticket;
        });
    }
}
