<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\TrialNurtureService;
use Illuminate\Console\Command;

class SendTrialNurtureCommand extends Command
{
    protected $signature = 'platform:send-trial-nurture';

    protected $description = 'Send scheduled trial nurture emails to workspace admins';

    public function handle(TrialNurtureService $nurture): int
    {
        $sent = $nurture->dispatchDueEmails();

        $this->info("Sent {$sent} trial nurture email(s).");

        return self::SUCCESS;
    }
}
