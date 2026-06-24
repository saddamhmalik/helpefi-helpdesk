<?php

namespace App\Domains\Tickets\Repositories;

use App\Domains\Tickets\Models\TicketAgentRead;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

class TicketReadRepository
{
    public function countsForTickets(int $userId, array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $tracked = TicketAgentRead::query()
            ->where('user_id', $userId)
            ->whereIn('ticket_id', $ticketIds)
            ->pluck('unread_count', 'ticket_id');

        $counts = [];

        foreach ($ticketIds as $ticketId) {
            $counts[$ticketId] = (int) ($tracked[$ticketId] ?? $this->fallbackUnreadCount($userId, $ticketId));
        }

        return $counts;
    }

    public function countForTicket(int $userId, int $ticketId): int
    {
        return $this->countsForTickets($userId, [$ticketId])[$ticketId] ?? 0;
    }

    public function markAsRead(int $userId, int $ticketId, ?int $messageId = null): void
    {
        $messageId ??= TicketMessage::query()
            ->where('ticket_id', $ticketId)
            ->max('id');

        TicketAgentRead::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'ticket_id' => $ticketId,
            ],
            [
                'last_read_message_id' => $messageId,
                'read_at' => now(),
                'unread_count' => 0,
            ],
        );
    }

    public function incrementUnreadForCustomerMessage(int $ticketId): void
    {
        TicketAgentRead::query()
            ->where('ticket_id', $ticketId)
            ->increment('unread_count');
    }

    private function fallbackUnreadCount(int $userId, int $ticketId): int
    {
        return (int) DB::table('ticket_messages as tm')
            ->leftJoin('ticket_agent_reads as tar', function ($join) use ($userId, $ticketId) {
                $join->on('tar.ticket_id', '=', 'tm.ticket_id')
                    ->where('tar.user_id', '=', $userId)
                    ->where('tar.ticket_id', '=', $ticketId);
            })
            ->where('tm.ticket_id', $ticketId)
            ->whereNotNull('tm.contact_id')
            ->where('tm.is_internal', false)
            ->where(function ($query) {
                $query->whereNull('tar.last_read_message_id')
                    ->orWhereColumn('tm.id', '>', 'tar.last_read_message_id');
            })
            ->count();
    }
}
