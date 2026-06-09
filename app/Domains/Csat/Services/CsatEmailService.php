<?php

namespace App\Domains\Csat\Services;

use App\Domains\Channels\Jobs\SendCsatSurveyJob;
use App\Domains\Csat\Repositories\CsatSettingRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Repositories\TicketRepository;

class CsatEmailService
{
    public function __construct(
        private CsatService $csat,
        private CsatSettingRepository $settings,
        private TicketRepository $tickets,
        private CsatSurveyMailer $mailer,
    ) {
    }

    public function sendIfEligible(Ticket $ticket): void
    {
        if (! $this->shouldSend($ticket)) {
            return;
        }

        SendCsatSurveyJob::dispatch($ticket->id);
    }

    public function deliver(Ticket $ticket): void
    {
        $ticket = $this->tickets->find($ticket->id);

        if (! $this->shouldSend($ticket)) {
            return;
        }

        $this->mailer->send($ticket);

        $this->tickets->update($ticket, ['csat_email_sent_at' => now()]);
    }

    private function shouldSend(Ticket $ticket): bool
    {
        $ticket->loadMissing(['status', 'contact', 'csatResponse']);

        if (! $this->csat->isEnabled() || ! $this->settings->current()->email_enabled) {
            return false;
        }

        if (! $ticket->status?->is_closed || $ticket->merged_into_ticket_id) {
            return false;
        }

        if (! $ticket->contact?->email || $ticket->csatResponse || $ticket->csat_email_sent_at) {
            return false;
        }

        return true;
    }
}
