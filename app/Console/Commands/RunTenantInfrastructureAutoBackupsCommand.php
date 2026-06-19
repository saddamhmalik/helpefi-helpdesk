<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\TenantInfrastructureBackupService;
use Illuminate\Console\Command;

class RunTenantInfrastructureAutoBackupsCommand extends Command
{
    protected $signature = 'platform:run-tenant-infrastructure-backups';

    protected $description = 'Queue scheduled database backups to customer BYO storage buckets';

    public function handle(TenantInfrastructureBackupService $backups): int
    {
        $queued = $backups->processDueSchedules();

        $this->info("Queued {$queued} tenant infrastructure backup(s).");

        return self::SUCCESS;
    }
}
