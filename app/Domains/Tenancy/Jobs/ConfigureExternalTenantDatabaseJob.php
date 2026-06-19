<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Tenancy\Services\ExternalTenantDatabaseService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ConfigureExternalTenantDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Tenant $tenant)
    {
    }

    public function handle(
        TenantInfrastructureService $infrastructureService,
        ExternalTenantDatabaseService $externalDatabase,
    ): void {
        $infrastructure = $infrastructureService->resolveForTenant($this->tenant);

        if ($infrastructure === null || ! $infrastructure->usesExternalDatabase()) {
            return;
        }

        $externalDatabase->applyToTenant($this->tenant, $infrastructure);
    }
}
