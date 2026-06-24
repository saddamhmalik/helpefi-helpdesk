<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Support\Facades\DB;

class TicketMergePersistence
{
    public function __construct(
        private TicketRepository $tickets,
        private TicketStatusLookup $statusLookup,
    ) {
    }

    public function execute(Ticket $target, Ticket $source, int $userId, bool $importConversation = true): Ticket
    {
        DB::transaction(function () use ($target, $source, $userId, $importConversation) {
            if ($importConversation) {
                TicketMessage::query()
                    ->where('ticket_id', $source->id)
                    ->update([
                        'ticket_id' => $target->id,
                        'merged_from_ticket_id' => $source->id,
                    ]);

                TicketAttachment::query()
                    ->where('ticket_id', $source->id)
                    ->update(['ticket_id' => $target->id]);
            }

            $watcherIds = $source->watchers()->pluck('users.id')->all();

            if ($watcherIds) {
                $target->watchers()->syncWithoutDetaching($watcherIds);
            }

            $closedStatus = $this->statusLookup->firstClosed();

            $source->update([
                'merged_into_ticket_id' => $target->id,
                'ticket_status_id' => $closedStatus?->id ?? $source->ticket_status_id,
                'closed_at' => now(),
            ]);

            $mergeNote = $importConversation
                ? "Ticket {$source->number} was merged into this ticket."
                : "Ticket {$source->number} was merged into this ticket. Conversation was not imported.";

            $target->messages()->create([
                'user_id' => $userId,
                'body' => $mergeNote,
                'is_internal' => true,
            ]);
        });

        return $this->tickets->findForBroadcast($target->id);
    }
}
