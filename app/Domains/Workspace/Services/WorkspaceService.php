<?php

namespace App\Domains\Workspace\Services;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Services\TicketReadService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Workspace\Models\TicketComposerDraft;
use App\Domains\Realtime\Services\RealtimePublisher;
use App\Domains\Workspace\Repositories\WorkspaceRepository;
use App\Domains\Workspace\Services\TicketPresenceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Carbon;

class WorkspaceService
{
    public function __construct(
        private WorkspaceRepository $workspace,
        private TicketService $tickets,
        private TicketReadService $ticketReads,
        private TicketPresenceService $presence,
        private RealtimePublisher $realtime,
    ) {
    }

    public function queue(array $filters, int $userId, int $perPage = 30): Paginator
    {
        return $this->ticketReads->attachUnreadCounts(
            $this->workspace->queue($filters, $perPage, $userId),
            $userId,
        );
    }

    public function markTicketRead(int $userId, int $ticketId, ?int $messageId = null): void
    {
        $this->ticketReads->markAsRead($userId, $ticketId, $messageId);
    }

    public function ticket(int $ticketId): Ticket
    {
        return $this->tickets->show($ticketId);
    }

    public function draft(int $userId, int $ticketId): ?TicketComposerDraft
    {
        return $this->workspace->draft($userId, $ticketId);
    }

    public function saveDraft(int $userId, int $ticketId, ?string $body, bool $isInternal): TicketComposerDraft
    {
        return $this->workspace->saveDraft($userId, $ticketId, $body, $isInternal);
    }

    public function reply(int $ticketId, int $userId, string $body, bool $isInternal = false): TicketMessage
    {
        $message = $this->tickets->reply($ticketId, $userId, $body, $isInternal);
        $this->workspace->clearDraft($userId, $ticketId);

        return $message->load('user:id,name');
    }

    public function quickUpdate(int $ticketId, array $data): Ticket
    {
        return $this->tickets->update($ticketId, $data);
    }

    public function pollTicket(int $ticketId, ?string $since, ?int $viewerId = null, ?int $sincePulse = null, bool $markRead = true): array
    {
        $ticketChanged = $this->presence->pulseSince($ticketId, $sincePulse);
        $sinceAt = $this->parseSince($since);

        $newMessages = $sinceAt
            ? $this->workspace->messagesSince($ticketId, $sinceAt)
            : [];

        if ($markRead && $viewerId) {
            $latestMessageId = collect($newMessages)->last()?->id;

            if ($latestMessageId === null && ! $sinceAt) {
                $latestMessageId = TicketMessage::query()->where('ticket_id', $ticketId)->max('id');
            }

            if ($latestMessageId) {
                $this->ticketReads->markAsRead($viewerId, $ticketId, $latestMessageId);
            }
        }

        return [
            'ticket' => $ticketChanged ? $this->tickets->show($ticketId) : null,
            'new_messages' => $newMessages,
            'viewers' => $this->presence->viewers($ticketId, $viewerId),
            'ticket_changed' => $ticketChanged,
            'unread_count' => 0,
            'server_time' => now()->toIso8601String(),
            'pulse' => now()->timestamp,
        ];
    }

    public function heartbeat(int $ticketId, int $userId, string $name, bool $composing = false): void
    {
        $this->presence->heartbeat($ticketId, $userId, $name, $composing);

        $this->realtime->presenceUpdated(
            $ticketId,
            $this->presence->viewers($ticketId, $userId),
        );
    }

    public function leaveTicket(int $ticketId, int $userId): void
    {
        $this->presence->leave($ticketId, $userId);

        $this->realtime->presenceUpdated(
            $ticketId,
            $this->presence->viewers($ticketId),
        );
    }

    public function pollQueue(?string $since, int $userId, array $ticketIds = []): array
    {
        $sinceAt = $this->parseSince($since) ?? now()->subMinute();

        return [
            'tickets' => $this->workspace->queueChangesSince($sinceAt),
            'unread_counts' => $this->ticketReads->countsForTickets($userId, $ticketIds),
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function parseSince(?string $since): ?Carbon
    {
        if (! $since) {
            return null;
        }

        return Carbon::parse(str_replace(' ', '+', urldecode($since)));
    }
}
