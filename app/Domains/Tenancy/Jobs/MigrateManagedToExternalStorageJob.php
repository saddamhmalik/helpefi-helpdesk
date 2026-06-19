<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Platform\Concerns\RunsOnCentralQueue;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Services\TenantInfrastructureMigrationService;
use App\Domains\Tenancy\Support\TenantInfrastructureUserMessage;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MigrateManagedToExternalStorageJob implements ShouldQueue
{
    use Queueable;
    use RunsOnCentralQueue;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public string $tenantId)
    {
        $this->bindToCentralQueue();
    }

    public function handle(TenantInfrastructureMigrationService $migrations): void
    {
        $this->ensureCentralContext();

        $tenant = Tenant::query()->findOrFail($this->tenantId);

        $migrations->runStorageMigration($tenant);
    }

    public function failed(?Throwable $exception): void
    {
        $this->ensureCentralContext();

        $record = app(TenantInfrastructureRepository::class)->findForTenant($this->tenantId);

        if ($record === null || ! $record->hasPendingStorageMigration()) {
            return;
        }

        $record->storage_migration_status = TenantInfrastructure::MIGRATION_FAILED;
        $record->status = TenantInfrastructure::STATUS_FAILED;
        $record->status_message = TenantInfrastructureUserMessage::migrationFailed(
            'storage',
            $exception?->getMessage(),
        );
        app(TenantInfrastructureRepository::class)->save($record);
    }
}
