<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Jobs\MigrateManagedToExternalDatabaseJob;
use App\Domains\Tenancy\Jobs\MigrateManagedToExternalStorageJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TenantInfrastructureMigrationService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private ManagedToExternalDatabaseMigrationService $databaseMigration,
        private ManagedToExternalStorageMigrationService $storageMigration,
    ) {
    }

    public function queueDatabaseMigration(Tenant $tenant): void
    {
        $record = $this->requireRecord($tenant);

        if ($record->database_mode === TenantInfrastructure::MODE_EXTERNAL) {
            throw ValidationException::withMessages([
                'infrastructure' => 'This workspace already uses an external database.',
            ]);
        }

        if (! $record->hasDatabaseTargetConfig()) {
            throw ValidationException::withMessages([
                'database_config' => 'Save external database credentials before starting migration.',
            ]);
        }

        if (in_array($record->database_migration_status, [
            TenantInfrastructure::MIGRATION_QUEUED,
            TenantInfrastructure::MIGRATION_RUNNING,
        ], true)) {
            throw ValidationException::withMessages([
                'infrastructure' => 'A database migration is already queued or running.',
            ]);
        }

        $record->database_migration_status = TenantInfrastructure::MIGRATION_QUEUED;
        $record->status = TenantInfrastructure::STATUS_PENDING;
        $record->status_message = 'Database migration queued.';
        $this->infrastructure->save($record);

        MigrateManagedToExternalDatabaseJob::dispatch($tenant->id);
    }

    public function queueStorageMigration(Tenant $tenant): void
    {
        $record = $this->requireRecord($tenant);

        if ($record->storage_mode === TenantInfrastructure::MODE_EXTERNAL) {
            throw ValidationException::withMessages([
                'infrastructure' => 'This workspace already uses external storage.',
            ]);
        }

        if (! $record->hasStorageTargetConfig()) {
            throw ValidationException::withMessages([
                'storage_config' => 'Save external storage credentials before starting migration.',
            ]);
        }

        if (in_array($record->storage_migration_status, [
            TenantInfrastructure::MIGRATION_QUEUED,
            TenantInfrastructure::MIGRATION_RUNNING,
        ], true)) {
            throw ValidationException::withMessages([
                'infrastructure' => 'A storage migration is already queued or running.',
            ]);
        }

        $record->storage_migration_status = TenantInfrastructure::MIGRATION_QUEUED;
        $record->status = TenantInfrastructure::STATUS_PENDING;
        $record->status_message = 'Storage migration queued.';
        $this->infrastructure->save($record);

        MigrateManagedToExternalStorageJob::dispatch($tenant->id);
    }

    public function runDatabaseMigration(Tenant $tenant): void
    {
        $record = $this->requireRecord($tenant);

        if ($record->database_migration_status !== TenantInfrastructure::MIGRATION_QUEUED) {
            return;
        }

        $record->database_migration_status = TenantInfrastructure::MIGRATION_RUNNING;
        $record->status_message = 'Database migration in progress.';
        $this->infrastructure->save($record);

        $this->databaseMigration->migrate($tenant, $record->fresh());
    }

    public function runStorageMigration(Tenant $tenant): void
    {
        $record = $this->requireRecord($tenant);

        if ($record->storage_migration_status !== TenantInfrastructure::MIGRATION_QUEUED) {
            return;
        }

        $record->storage_migration_status = TenantInfrastructure::MIGRATION_RUNNING;
        $record->status_message = 'Storage migration in progress.';
        $this->infrastructure->save($record);

        $this->storageMigration->migrate($tenant, $record->fresh());
    }

    private function requireRecord(Tenant $tenant): TenantInfrastructure
    {
        $record = $this->infrastructure->findForTenant($tenant->id);

        if ($record === null) {
            throw new RuntimeException('Infrastructure is not configured for this workspace.');
        }

        return $record;
    }
}
