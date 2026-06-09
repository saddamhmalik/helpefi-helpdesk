<?php

namespace App\Console\Commands;

use App\Domains\Reports\Services\ReportScheduleService;
use Illuminate\Console\Command;

class DispatchScheduledReportsCommand extends Command
{
    protected $signature = 'reports:dispatch-scheduled';

    protected $description = 'Queue due scheduled report deliveries';

    public function handle(ReportScheduleService $schedules): int
    {
        $count = $schedules->dispatchDue();

        $this->info("Queued {$count} scheduled report(s).");

        return self::SUCCESS;
    }
}
