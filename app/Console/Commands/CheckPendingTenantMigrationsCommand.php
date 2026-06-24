<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\TenantPendingMigrationService;
use Illuminate\Console\Command;

class CheckPendingTenantMigrationsCommand extends Command
{
    protected $signature = 'platform:check-pending-tenant-migrations';

    protected $description = 'Alert when workspaces have pending schema or release upgrades';

    public function handle(TenantPendingMigrationService $migrations): int
    {
        $alerted = $migrations->checkAll();

        $this->info("Alerted on {$alerted} workspace(s) with pending schema or release upgrades.");

        return self::SUCCESS;
    }
}
