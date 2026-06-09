<?php

namespace App\Domains\Channels\Jobs;

use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTicketReplyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $ticketId,
        public int $messageId,
        public int $agentId,
    ) {
        $this->afterCommit();
    }

    public function handle(OutboundMailService $mail, TicketRepository $tickets): void
    {
        $ticket = $tickets->find($this->ticketId);
        $message = $ticket->messages()->findOrFail($this->messageId);
        $agent = User::query()->findOrFail($this->agentId);

        $mail->deliverTicketReply($ticket, $message, $agent);
    }
}
