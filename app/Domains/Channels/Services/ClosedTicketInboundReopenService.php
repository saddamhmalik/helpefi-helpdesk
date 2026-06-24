<?php

namespace App\Domains\Channels\Services;

use App\Domains\Tickets\Events\TicketUpdated;
use App\Domains\Channels\Support\ThankYouMessageDetector;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Services\TicketStatusLookup;

class ClosedTicketInboundReopenService
{
    public function __construct(
        private TicketRepository $tickets,
        private HelpdeskSettingService $helpdeskSettings,
        private ThankYouMessageDetector $thankYouDetector,
        private AuditRecorder $audit,
        private TicketStatusLookup $statusLookup,
    ) {
    }

    public function maybeReopen(Ticket $ticket, string $messageBody): bool
    {
        return $this->maybeReopenOnCustomerMessage($ticket, $messageBody, true);
    }

    public function maybeReopenOnCustomerMessage(Ticket $ticket, string $messageBody, bool $fromEmail = false): bool
    {
        if ($fromEmail && ! $this->helpdeskSettings->emailReopenClosedOnInbound()) {
            return false;
        }

        $ticket->loadMissing('status');

        if (! $ticket->status?->is_closed) {
            return false;
        }

        if ($fromEmail
            && $this->helpdeskSettings->emailSuppressReopenOnThankYou()
            && $this->thankYouDetector->isThankYouNote($messageBody)) {
            return false;
        }

        return $this->reopen($ticket, $fromEmail ? 'ticket.reopened_via_email' : 'ticket.reopened_via_customer_reply', [
            'from_status_id' => $ticket->ticket_status_id,
            'reopened_via_email' => $fromEmail,
            'reopened_via_customer' => true,
        ]);
    }

    public function maybeReopenOnAgentReply(Ticket $ticket): bool
    {
        $ticket->loadMissing('status');

        if (! $ticket->status?->is_closed) {
            return false;
        }

        return $this->reopen($ticket, 'ticket.reopened_via_agent_reply', [
            'from_status_id' => $ticket->ticket_status_id,
            'reopened_via_agent' => true,
        ]);
    }

    private function reopen(Ticket $ticket, string $auditEvent, array $auditMeta): bool
    {
        $openStatus = $this->statusLookup->defaultOpen();

        if (! $openStatus) {
            return false;
        }

        $beforeStatusId = $ticket->ticket_status_id;

        $closedIds = $this->statusLookup->closedIds();

        if ($closedIds->isEmpty()) {
            return false;
        }

        $updated = Ticket::query()
            ->whereKey($ticket->id)
            ->whereIn('ticket_status_id', $closedIds)
            ->update([
                'ticket_status_id' => $openStatus->id,
                'closed_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return false;
        }

        $ticket = Ticket::query()->findOrFail($ticket->id);

        $this->audit->record($auditEvent, $ticket, array_merge([
            'number' => $ticket->number,
            'from_status_id' => $beforeStatusId,
            'to_status_id' => $openStatus->id,
        ], $auditMeta));

        TicketUpdated::dispatch($ticket, [
            'changed' => ['ticket_status_id'],
            'reopened_via_reply' => true,
        ]);

        return true;
    }
}
