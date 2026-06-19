<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\DatabaseBackupExporter;
use App\Domains\Platform\Support\DatabaseBackupImporter;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Support\TenantInfrastructureUserMessage;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ManagedToExternalDatabaseMigrationService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private ExternalTenantDatabaseService $externalDatabase,
        private DatabaseBackupExporter $exporter,
        private DatabaseBackupImporter $importer,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function migrate(Tenant $tenant, TenantInfrastructure $record): void
    {
        $targetConfig = $record->database_config ?? [];

        if ($targetConfig === []) {
            throw new RuntimeException('External database configuration is missing.');
        }

        $connectionError = $this->externalDatabase->testConnection($targetConfig);

        if ($connectionError !== null) {
            throw new RuntimeException($connectionError);
        }

        $managedDatabaseName = $tenant->database()->getName();
        $dumpPath = storage_path('app/tenant-migrations/'.$tenant->id.'-'.now()->format('YmdHis').'.sql');
        $directory = dirname($dumpPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        try {
            $tenant->run(function () use ($dumpPath) {
                $this->exporter->exportConnection(config('database.default'), $dumpPath);
            });

            $this->importer->importMysql($targetConfig, $dumpPath);

            $record->database_mode = TenantInfrastructure::MODE_EXTERNAL;
            $record->database_migration_status = TenantInfrastructure::MIGRATION_COMPLETED;
            $record->status = TenantInfrastructure::STATUS_PENDING;
            $record->status_message = 'Database migration completed. Run verify to confirm the external connection.';
            $this->infrastructure->save($record);

            $this->externalDatabase->applyToTenant($tenant->fresh(), $record->fresh());

            if ((bool) config('tenant_infrastructure.drop_managed_database_after_migration', false)) {
                $this->dropManagedDatabase($managedDatabaseName);
            }

            $this->audit->record(
                'platform.tenant.infrastructure_database_migrated',
                $tenant,
                [
                    'managed_database' => $managedDatabaseName,
                    'external_database' => $targetConfig['database'] ?? null,
                ],
                tenantId: $tenant->id,
            );
        } catch (Throwable $exception) {
            $record->database_migration_status = TenantInfrastructure::MIGRATION_FAILED;
            $record->status = TenantInfrastructure::STATUS_FAILED;
            $record->status_message = TenantInfrastructureUserMessage::migrationFailed(
                'database',
                $exception->getMessage(),
            );
            $this->infrastructure->save($record);

            $this->audit->record(
                'platform.tenant.infrastructure_migration_failed',
                $tenant,
                [
                    'type' => 'database',
                    'message' => $exception->getMessage(),
                ],
                tenantId: $tenant->id,
            );

            throw $exception;
        } finally {
            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
        }
    }

    private function dropManagedDatabase(string $databaseName): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
            return;
        }

        DB::connection(config('tenancy.database.central_connection', 'central'))
            ->statement('DROP DATABASE IF EXISTS `'.$databaseName.'`');
    }
}
