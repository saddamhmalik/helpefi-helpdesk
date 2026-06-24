<?php

namespace App\Domains\Tenancy\Releases\V1_0_0;

use App\Domains\Tenancy\Releases\AbstractTenantReleaseStep;
use App\Domains\Tenancy\Services\TenantWorkspaceUpgradeService;

class NormalizeHandbookMetadataStep extends AbstractTenantReleaseStep
{
    public function key(): string
    {
        return 'normalize_handbook_metadata';
    }

    public function description(): string
    {
        return 'Normalize platform handbook collection and article visibility flags.';
    }

    public function run(): void
    {
        app(TenantWorkspaceUpgradeService::class)->normalizePlatformHandbookMetadata();
    }
}
