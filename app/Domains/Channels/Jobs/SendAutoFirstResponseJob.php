<?php

namespace App\Domains\Channels\Jobs;

use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAutoFirstResponseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $ticketId,
        public int $messageId,
    ) {
        $this->afterCommit();
    }

    public function handle(OutboundMailService $mail, TicketRepository $tickets): void
    {
        $ticket = $tickets->find($this->ticketId);
        $message = $ticket->messages()->findOrFail($this->messageId);

        $mail->deliverAutoFirstResponse($ticket, $message);
    }
}
