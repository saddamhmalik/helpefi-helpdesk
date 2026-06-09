<?php

namespace App\Domains\Channels\Repositories;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Services\EmailThreadService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Database\Eloquent\Collection;

class ChannelRepository
{
    public function all(): Collection
    {
        return Channel::query()->orderBy('name')->get();
    }

    public function find(int $id): Channel
    {
        return Channel::query()->findOrFail($id);
    }

    public function findBySlug(string $slug): ?Channel
    {
        return Channel::query()->where('slug', $slug)->first();
    }

    public function findActiveBySlug(string $slug): ?Channel
    {
        return Channel::query()->where('slug', $slug)->where('is_active', true)->first();
    }

    public function update(Channel $channel, array $data): Channel
    {
        $channel->update($data);

        return $channel->fresh();
    }

    public function findTicketByNumber(string $number): ?Ticket
    {
        return Ticket::query()
            ->where('number', $number)
            ->whereNull('merged_into_ticket_id')
            ->first();
    }

    public function messageExistsByExternalId(int $channelId, string $externalId): bool
    {
        return TicketMessage::query()
            ->where('channel_id', $channelId)
            ->where('external_id', EmailThreadService::normalizeMessageId($externalId))
            ->exists();
    }

    public function findTicketByMessageReferences(int $channelId, array $messageIds): ?Ticket
    {
        $normalized = array_values(array_filter(array_map(
            [EmailThreadService::class, 'normalizeMessageId'],
            $messageIds,
        )));

        if ($normalized === []) {
            return null;
        }

        $ticketId = TicketMessage::query()
            ->where('channel_id', $channelId)
            ->whereIn('external_id', $normalized)
            ->orderByDesc('id')
            ->value('ticket_id');

        if (! $ticketId) {
            return null;
        }

        return Ticket::query()
            ->where('id', $ticketId)
            ->whereNull('merged_into_ticket_id')
            ->first();
    }

    public function findTicketBySubjectAndContact(int $channelId, int $contactId, int $inboxId, string $subject): ?Ticket
    {
        if ($subject === '') {
            return null;
        }

        foreach ($this->recentEmailTicketsForContact($channelId, $contactId, $inboxId) as $ticket) {
            if (EmailThreadService::normalizeSubject($ticket->subject) === $subject) {
                return $ticket;
            }
        }

        return null;
    }

    public function recentEmailTicketsForContact(int $channelId, int $contactId, int $inboxId, int $limit = 15): Collection
    {
        return Ticket::query()
            ->where('channel_id', $channelId)
            ->where('contact_id', $contactId)
            ->where('email_inbox_id', $inboxId)
            ->whereNull('merged_into_ticket_id')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    public function findTicketByCcEmail(int $channelId, string $email): ?Ticket
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return Ticket::query()
            ->where('channel_id', $channelId)
            ->whereNull('merged_into_ticket_id')
            ->whereHas('ccs', fn ($query) => $query->where('email', $email))
            ->orderByDesc('updated_at')
            ->first();
    }
}
