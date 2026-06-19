<?php

namespace App\Domains\Tenancy\Bootstrappers;

use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Services\ExternalTenantStorageService;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class TenantExternalStorageBootstrapper implements TenancyBootstrapper
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private ExternalTenantStorageService $storage,
    ) {
    }

    public function bootstrap(Tenant $tenant): void
    {
        $record = $this->infrastructure->findForTenant($tenant->getTenantKey());

        if ($record === null || ! $record->usesExternalStorage()) {
            return;
        }

        $this->storage->registerDisk($record);
    }

    public function revert(): void
    {
        $this->storage->unregisterDisk();
    }
}
