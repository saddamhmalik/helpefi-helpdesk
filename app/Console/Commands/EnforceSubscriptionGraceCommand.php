<?php

namespace App\Console\Commands;

use App\Domains\Billing\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class EnforceSubscriptionGraceCommand extends Command
{
    protected $signature = 'billing:enforce-grace';

    protected $description = 'Block workspaces whose post-cancellation grace period has ended';

    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        $blocked = $lifecycle->enforceExpiredGrace();

        $this->info("Blocked {$blocked} workspace(s) after grace period.");

        return self::SUCCESS;
    }
}
