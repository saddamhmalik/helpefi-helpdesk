<?php

namespace App\Domains\Tenancy\Releases\V1_0_0;

use App\Domains\Tenancy\Releases\AbstractTenantReleaseStep;
use App\Domains\Tenancy\Services\TenantWorkspaceUpgradeService;

class SyncPlatformHandbookStep extends AbstractTenantReleaseStep
{
    public function key(): string
    {
        return 'sync_platform_handbook';
    }

    public function description(): string
    {
        return 'Seed or refresh the platform How-to handbook content.';
    }

    public function run(): void
    {
        app(TenantWorkspaceUpgradeService::class)->ensurePlatformHandbook();
    }
}
