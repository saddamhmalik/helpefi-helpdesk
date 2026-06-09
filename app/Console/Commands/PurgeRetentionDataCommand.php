<?php

namespace App\Console\Commands;

use App\Domains\Security\Services\RetentionService;
use Illuminate\Console\Command;

class PurgeRetentionDataCommand extends Command
{
    protected $signature = 'security:purge-retention';

    protected $description = 'Purge audit logs and closed tickets past retention thresholds';

    public function handle(RetentionService $retention): int
    {
        $results = $retention->purge();

        $this->info(sprintf(
            'Purged %d audit logs, %d tickets, %d messages.',
            $results['audit_logs'],
            $results['tickets'],
            $results['messages'],
        ));

        return self::SUCCESS;
    }
}
