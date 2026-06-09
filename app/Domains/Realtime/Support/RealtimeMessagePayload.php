<?php

namespace App\Domains\Realtime\Support;

use App\Domains\Chat\Models\ChatSession;
use App\Domains\Tickets\Models\TicketMessage;

class RealtimeMessagePayload
{
    public static function fromMessage(TicketMessage $message): array
    {
        $message->loadMissing(['user:id,name', 'contact:id,name,email']);

        return [
            'id' => $message->id,
            'ticket_id' => $message->ticket_id,
            'body' => $message->body,
            'is_internal' => $message->is_internal,
            'user_id' => $message->user_id,
            'contact_id' => $message->contact_id,
            'author_type' => $message->user_id ? 'agent' : 'visitor',
            'author_name' => $message->user_id
                ? ($message->user?->name ?? 'Agent')
                : ($message->contact?->name ?? 'Visitor'),
            'user' => $message->user ? ['id' => $message->user->id, 'name' => $message->user->name] : null,
            'contact' => $message->contact ? [
                'id' => $message->contact->id,
                'name' => $message->contact->name,
                'email' => $message->contact->email,
            ] : null,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    public static function chatSessionUuidForTicket(int $ticketId): ?string
    {
        return ChatSession::query()
            ->where('ticket_id', $ticketId)
            ->whereNull('closed_at')
            ->value('uuid');
    }
}
