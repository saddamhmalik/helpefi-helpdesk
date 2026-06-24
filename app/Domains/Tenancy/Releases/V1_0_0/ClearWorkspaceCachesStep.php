<?php

namespace App\Domains\Tenancy\Releases\V1_0_0;

use App\Domains\Tenancy\Releases\AbstractTenantReleaseStep;
use App\Domains\Tenancy\Services\TenantWorkspaceUpgradeService;

class ClearWorkspaceCachesStep extends AbstractTenantReleaseStep
{
    public function key(): string
    {
        return 'clear_workspace_caches';
    }

    public function description(): string
    {
        return 'Clear help center and reference caches after data upgrades.';
    }

    public function run(): void
    {
        app(TenantWorkspaceUpgradeService::class)->clearWorkspaceCaches();
    }
}
