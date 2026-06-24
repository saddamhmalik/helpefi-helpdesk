<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Support\Facades\DB;

class TicketSplitPersistence
{
    public function __construct(
        private TicketRepository $tickets,
    ) {
    }

    public function execute(Ticket $ticket, int $fromMessageId, int $userId, ?string $subject = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $fromMessageId, $userId, $subject) {
            $message = $ticket->messages()->findOrFail($fromMessageId);
            $messageIds = $ticket->messages()
                ->where('created_at', '>=', $message->created_at)
                ->pluck('id');

            $newTicket = $this->tickets->create([
                'subject' => $subject ?? "Split from {$ticket->number}",
                'description' => null,
                'contact_id' => $ticket->contact_id,
                'assigned_to' => $ticket->assigned_to,
                'department_id' => $ticket->department_id,
                'team_id' => $ticket->team_id,
                'channel_id' => $ticket->channel_id,
                'brand_id' => $ticket->brand_id,
                'email_inbox_id' => $ticket->email_inbox_id,
                'ticket_status_id' => $ticket->ticket_status_id,
                'ticket_priority_id' => $ticket->ticket_priority_id,
                'type' => $ticket->type,
            ]);

            TicketMessage::query()->whereIn('id', $messageIds)->update(['ticket_id' => $newTicket->id]);

            TicketAttachment::query()
                ->whereIn('ticket_message_id', $messageIds)
                ->update(['ticket_id' => $newTicket->id]);

            $ticket->messages()->create([
                'user_id' => $userId,
                'body' => "Split messages into ticket {$newTicket->number}.",
                'is_internal' => true,
            ]);

            $newTicket->messages()->create([
                'user_id' => $userId,
                'body' => "Created by split from ticket {$ticket->number}.",
                'is_internal' => true,
            ]);

            return $this->tickets->find($newTicket->id);
        });
    }
}
