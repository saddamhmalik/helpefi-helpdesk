<?php

namespace App\Domains\Channels\Jobs;

use App\Domains\Channels\Jobs\SendCsatSurveyJob;
use App\Domains\Csat\Services\CsatEmailService;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCsatSurveyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $ticketId,
    ) {
        $this->afterCommit();
    }

    public function handle(CsatEmailService $csatEmail): void
    {
        $ticket = Ticket::query()->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $csatEmail->deliver($ticket);
    }
}
