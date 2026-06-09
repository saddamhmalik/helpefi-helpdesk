<?php

namespace App\Console\Commands;

use App\Domains\Sla\Services\SlaEscalationService;
use App\Domains\Sla\Services\SlaService;
use Illuminate\Console\Command;

class CheckSlaBreachesCommand extends Command
{
    protected $signature = 'sla:check-breaches';

    protected $description = 'Mark SLA breaches and run escalation rules';

    public function handle(SlaService $slaService, SlaEscalationService $escalations): int
    {
        $breaches = $slaService->checkBreaches();
        $escalated = $escalations->processEscalations();

        $this->info("Marked {$breaches} SLA breach(es), processed {$escalated} escalation(s).");

        return self::SUCCESS;
    }
}
