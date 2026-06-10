<?php

namespace App\Domains\Ai\Jobs;

use App\Domains\Ai\Services\AiTriageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TriageTicketJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ticketId)
    {
    }

    public function handle(AiTriageService $triage): void
    {
        $triage->triage($this->ticketId);
    }
}
