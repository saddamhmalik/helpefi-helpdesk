<?php

namespace App\Console\Commands;

use App\Domains\Automation\Services\AutomationService;
use Illuminate\Console\Command;

class ProcessAutomationScheduledActionsCommand extends Command
{
    protected $signature = 'automation:process-scheduled';

    protected $description = 'Run due delayed automation actions';

    public function handle(AutomationService $automation): int
    {
        $count = $automation->processDueScheduled();

        $this->info("Processed {$count} scheduled automation action(s).");

        return self::SUCCESS;
    }
}
