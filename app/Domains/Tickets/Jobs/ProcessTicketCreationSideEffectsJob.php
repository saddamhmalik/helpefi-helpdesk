<?php

namespace App\Domains\Tickets\Jobs;

use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\ServiceDesk\Services\ChangeRecordService;
use App\Domains\ServiceDesk\Services\ProblemRecordService;
use App\Domains\Tickets\Repositories\TicketRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTicketCreationSideEffectsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $ticketId,
        public ?int $userId = null,
    ) {
        $this->afterCommit();
    }

    public function handle(
        ApprovalService $approvals,
        ChangeRecordService $changeRecords,
        ProblemRecordService $problemRecords,
        TicketRepository $tickets,
    ): void {
        $ticket = $tickets->findForWrite($this->ticketId);

        $approvals->evaluateForNewTicket($ticket, $this->userId);
        $changeRecords->ensureForTicket($ticket);
        $problemRecords->ensureForTicket($ticket);
    }
}
