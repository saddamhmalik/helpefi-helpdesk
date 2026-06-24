<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Realtime\Services\RealtimePublisher;
use App\Domains\Realtime\Support\RealtimeMessagePayload;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;

class TicketRealtimeBroadcaster
{
    public function __construct(private RealtimePublisher $realtime)
    {
    }

    public function broadcastMessage(TicketMessage $message, bool $includeChatChannel): void
    {
        $message->loadMissing(['user:id,name,email,avatar_type,avatar_path', 'contact:id,name']);

        $this->realtime->ticketMessage(
            $message->ticket_id,
            RealtimeMessagePayload::fromMessage($message),
            $includeChatChannel && ! $message->is_internal
                ? RealtimeMessagePayload::chatSessionUuidForTicket($message->ticket_id)
                : null,
        );
    }

    public function broadcastTicketSnapshot(Ticket $ticket): void
    {
        $ticket->loadMissing([
            'status:id,name,slug,color',
            'priority:id,name,slug',
            'contact:id,name,email',
            'assignee:id,name',
        ]);

        $this->realtime->ticketUpdated($ticket->id, [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'ticket_status_id' => $ticket->ticket_status_id,
            'ticket_priority_id' => $ticket->ticket_priority_id,
            'assigned_to' => $ticket->assigned_to,
            'snoozed_until' => $ticket->snoozed_until?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'contact' => $ticket->contact,
            'assignee' => $ticket->assignee,
        ]);
    }
}
