<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Realtime\Services\RealtimePublisher;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketSnoozeRepository;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TicketSnoozeService
{
    public function __construct(
        private TicketSnoozeRepository $snoozes,
        private AuditRecorder $audit,
        private RealtimePublisher $realtime,
    ) {
    }

    public function snooze(int $ticketId, Carbon $until, int $userId): Ticket
    {
        if ($until->isPast()) {
            throw new InvalidArgumentException('Snooze time must be in the future.');
        }

        $ticket = $this->snoozes->setSnooze($this->snoozes->find($ticketId), $until);

        $this->audit->record('ticket.snoozed', $ticket, [
            'number' => $ticket->number,
            'snoozed_until' => $until->toIso8601String(),
        ], $userId);

        $this->broadcast($ticket);

        return $ticket;
    }

    public function unsnooze(int $ticketId, int $userId): Ticket
    {
        $ticket = $this->snoozes->setSnooze($this->snoozes->find($ticketId), null);

        $this->audit->record('ticket.unsnoozed', $ticket, [
            'number' => $ticket->number,
        ], $userId);

        $this->broadcast($ticket);

        return $ticket;
    }

    public function releaseExpired(): int
    {
        $expired = $this->snoozes->expiredSnoozes();
        $count = $this->snoozes->clearExpired();

        foreach ($expired as $ticket) {
            $ticket->snoozed_until = null;
            $this->broadcast($ticket);
        }

        return $count;
    }

    private function broadcast(Ticket $ticket): void
    {
        $ticket->loadMissing([
            'status:id,name,slug,color',
            'priority:id,name,slug',
            'contact:id,name,email',
            'assignee:id,name',
        ]);

        $snapshot = [
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
        ];

        $this->realtime->ticketUpdated($ticket->id, $snapshot);
    }
}
