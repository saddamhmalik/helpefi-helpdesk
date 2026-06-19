<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\TenantInfrastructureHealthService;
use Illuminate\Console\Command;

class CheckTenantInfrastructureCommand extends Command
{
    protected $signature = 'platform:check-tenant-infrastructure';

    protected $description = 'Run health checks for workspaces with external database or storage';

    public function handle(TenantInfrastructureHealthService $health): int
    {
        $checked = $health->checkAll();

        $this->info("Checked {$checked} workspace(s) with external infrastructure.");

        return self::SUCCESS;
    }
}
