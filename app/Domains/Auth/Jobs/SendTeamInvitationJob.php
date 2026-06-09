<?php

namespace App\Domains\Auth\Jobs;

use App\Domains\Auth\Services\InvitationMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTeamInvitationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $invitationId)
    {
        $this->afterCommit();
    }

    public function handle(InvitationMailService $mail): void
    {
        $mail->deliver($this->invitationId);
    }
}
