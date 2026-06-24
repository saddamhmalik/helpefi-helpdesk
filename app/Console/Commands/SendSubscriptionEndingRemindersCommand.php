<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\SubscriptionEndingReminderService;
use Illuminate\Console\Command;

class SendSubscriptionEndingRemindersCommand extends Command
{
    protected $signature = 'platform:send-subscription-ending-reminders';

    protected $description = 'Send scheduled subscription ending reminder emails to workspace admins';

    public function handle(SubscriptionEndingReminderService $reminders): int
    {
        $sent = $reminders->dispatchDueEmails();

        $this->info("Sent {$sent} subscription ending reminder email(s).");

        return self::SUCCESS;
    }
}
