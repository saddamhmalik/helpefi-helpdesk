<?php

namespace App\Domains\Tickets\Jobs;

use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Tickets\Repositories\TicketRepository;
use App\Domains\Tickets\Services\TicketExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTicketExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $ticketId,
        public string $email,
        public int $userId,
        public bool $includeConversation = true,
    ) {
        $this->afterCommit();
    }

    public function handle(
        TicketExportService $export,
        TicketRepository $tickets,
        OutboundMailService $outboundMail,
    ): void {
        $ticket = $tickets->find($this->ticketId);
        $pdf = $export->pdf($this->ticketId, $this->includeConversation)->output();

        $outboundMail->deliverTicketExport($ticket, $pdf, $this->email, $this->includeConversation);
    }
}
