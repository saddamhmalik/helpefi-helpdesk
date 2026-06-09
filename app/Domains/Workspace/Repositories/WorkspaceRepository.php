<?php

namespace App\Domains\Workspace\Repositories;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Workspace\Models\TicketComposerDraft;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class WorkspaceRepository
{
    public function queue(array $filters, int $perPage = 30, ?int $watchingUserId = null): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with(['contact:id,name,email', 'status:id,name,slug,color', 'priority:id,name,slug', 'assignee:id,name'])
            ->whereNull('merged_into_ticket_id')
            ->orderByDesc('updated_at');

        if (! empty($filters['status_id'])) {
            $query->where('ticket_status_id', $filters['status_id']);
        }

        if (! empty($filters['priority_id'])) {
            $query->where('ticket_priority_id', $filters['priority_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['watching']) && $watchingUserId) {
            $query->whereHas('watchers', fn ($q) => $q->where('user_id', $watchingUserId));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function queueChangesSince(Carbon $since, int $limit = 20): array
    {
        return Ticket::query()
            ->with(['status:id,name,slug,color', 'priority:id,name,slug'])
            ->whereNull('merged_into_ticket_id')
            ->where('updated_at', '>', $since)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'number', 'subject', 'ticket_status_id', 'ticket_priority_id', 'assigned_to', 'updated_at'])
            ->all();
    }

    public function messagesSince(int $ticketId, Carbon $since): array
    {
        return TicketMessage::query()
            ->where('ticket_id', $ticketId)
            ->where('created_at', '>', $since)
            ->with(['user:id,name', 'contact:id,name,email'])
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    public function draft(int $userId, int $ticketId): ?TicketComposerDraft
    {
        return TicketComposerDraft::query()
            ->where('user_id', $userId)
            ->where('ticket_id', $ticketId)
            ->first();
    }

    public function saveDraft(int $userId, int $ticketId, ?string $body, bool $isInternal): TicketComposerDraft
    {
        return TicketComposerDraft::query()->updateOrCreate(
            ['user_id' => $userId, 'ticket_id' => $ticketId],
            ['body' => $body, 'is_internal' => $isInternal],
        );
    }

    public function clearDraft(int $userId, int $ticketId): void
    {
        TicketComposerDraft::query()
            ->where('user_id', $userId)
            ->where('ticket_id', $ticketId)
            ->delete();
    }
}
