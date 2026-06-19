<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\DatabaseBackupExporter;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Jobs\ExportTenantDatabaseBackupJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Support\TenantInfrastructureUserMessage;
use App\Domains\Tenancy\Support\TenantStorageDisks;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExternalTenantBackupService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private ExternalTenantStorageService $externalStorage,
        private TenantInfrastructureBackupCatalogService $catalog,
        private DatabaseBackupExporter $exporter,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function queueExportDatabaseToCustomerBucket(Tenant $tenant, bool $scheduled = false): void
    {
        $record = $this->requireExportableInfrastructure($tenant);

        if ($record->hasPendingBackupExport()) {
            throw ValidationException::withMessages([
                'infrastructure' => 'A database backup export is already queued or running.',
            ]);
        }

        $record->backup_export_status = TenantInfrastructure::MIGRATION_QUEUED;
        $record->backup_export_message = $scheduled
            ? 'Scheduled database backup queued.'
            : 'Database backup export queued.';
        $this->infrastructure->save($record);

        ExportTenantDatabaseBackupJob::dispatch($tenant->id, $scheduled);
    }

    public function runExportDatabaseToCustomerBucket(Tenant $tenant, bool $scheduled = false): array
    {
        $record = $this->requireExportableInfrastructure($tenant);

        if ($record->backup_export_status !== TenantInfrastructure::MIGRATION_QUEUED) {
            return [
                'path' => $record->backup_export_path,
            ];
        }

        $record->backup_export_status = TenantInfrastructure::MIGRATION_RUNNING;
        $record->backup_export_message = $scheduled
            ? 'Scheduled database backup in progress.'
            : 'Database backup export in progress.';
        $this->infrastructure->save($record);

        $dumpPath = storage_path('app/tenant-backups/'.$tenant->id.'-'.now()->format('YmdHis').'.sql');
        $directory = dirname($dumpPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $objectKey = $this->catalog->backupObjectKey();

        try {
            $tenant->run(function () use ($dumpPath) {
                $this->exporter->exportConnection(config('database.default'), $dumpPath);
            });

            $this->externalStorage->registerDisk($record);

            $stream = fopen($dumpPath, 'r');

            if ($stream === false) {
                throw new RuntimeException('Failed to read database export file.');
            }

            Storage::disk(TenantStorageDisks::EXTERNAL)->writeStream($objectKey, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $size = filesize($dumpPath) ?: 0;

            $backup = $this->catalog->registerUploadedObject($tenant, $objectKey, $size);

            $meta = [
                'path' => $objectKey,
                'size' => $size,
                'backup_id' => $backup->id,
            ];

            $record->backup_export_status = TenantInfrastructure::MIGRATION_COMPLETED;
            $record->backup_export_path = $objectKey;
            $record->backup_export_message = $scheduled
                ? 'Scheduled database backup uploaded to your bucket.'
                : 'Database backup uploaded to your bucket.';

            if ($scheduled) {
                $record->auto_backup_last_run_at = now();
            }

            $this->infrastructure->save($record);

            $this->audit->record(
                'platform.tenant.infrastructure_backup_exported',
                $tenant,
                $meta,
                tenantId: $tenant->id,
            );

            return $meta;
        } catch (\Throwable $exception) {
            $record->backup_export_status = TenantInfrastructure::MIGRATION_FAILED;
            $record->backup_export_message = TenantInfrastructureUserMessage::backupExportFailed(
                $exception->getMessage(),
            );
            $this->infrastructure->save($record);

            throw $exception;
        } finally {
            $this->externalStorage->unregisterDisk();

            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
        }
    }

    private function requireExportableInfrastructure(Tenant $tenant): TenantInfrastructure
    {
        $record = $this->infrastructure->findForTenant($tenant->id);

        if ($record === null || ! $record->usesExternalDatabase()) {
            throw ValidationException::withMessages([
                'tenant' => 'Customer bucket export requires an external database workspace.',
            ]);
        }

        if (! $record->usesExternalStorage()) {
            throw ValidationException::withMessages([
                'tenant' => 'Customer bucket export requires external storage to be configured and verified.',
            ]);
        }

        return $record;
    }
}
