<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Platform\Concerns\RunsOnCentralQueue;
use App\Domains\Platform\Support\DatabaseMysqlCli;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Services\ExternalTenantBackupService;
use App\Domains\Tenancy\Support\TenantInfrastructureUserMessage;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportTenantDatabaseBackupJob implements ShouldQueue
{
    use Queueable;
    use RunsOnCentralQueue;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public string $tenantId,
        public bool $scheduled = false,
    ) {
        $this->timeout = DatabaseMysqlCli::processTimeout();
        $this->bindToCentralQueue();
    }

    public function handle(ExternalTenantBackupService $backups): void
    {
        $this->ensureCentralContext();

        $tenant = Tenant::query()->findOrFail($this->tenantId);

        $backups->runExportDatabaseToCustomerBucket($tenant, $this->scheduled);
    }

    public function failed(?Throwable $exception): void
    {
        $this->ensureCentralContext();

        $record = app(TenantInfrastructureRepository::class)->findForTenant($this->tenantId);

        if ($record === null || ! $record->hasPendingBackupExport()) {
            return;
        }

        $record->backup_export_status = TenantInfrastructure::MIGRATION_FAILED;
        $record->backup_export_message = TenantInfrastructureUserMessage::backupExportFailed(
            $exception?->getMessage(),
        );
        app(TenantInfrastructureRepository::class)->save($record);
    }
}
