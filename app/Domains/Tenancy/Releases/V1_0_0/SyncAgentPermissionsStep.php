<?php

namespace App\Domains\Tenancy\Releases\V1_0_0;

use App\Domains\Tenancy\Releases\AbstractTenantReleaseStep;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;

class SyncAgentPermissionsStep extends AbstractTenantReleaseStep
{
    public function key(): string
    {
        return 'sync_agent_permissions';
    }

    public function description(): string
    {
        return 'Sync default agent role permissions.';
    }

    public function run(): void
    {
        Artisan::call('db:seed', [
            '--class' => PermissionSeeder::class,
            '--force' => true,
        ]);
    }
}
