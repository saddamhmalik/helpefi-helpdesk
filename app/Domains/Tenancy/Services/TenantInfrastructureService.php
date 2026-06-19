<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Jobs\MigrateManagedToExternalDatabaseJob;
use App\Domains\Tenancy\Jobs\MigrateManagedToExternalStorageJob;
use App\Domains\Tenancy\Jobs\VerifyTenantInfrastructureJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Models\Tenant;
use App\Domains\Tenancy\Support\TenantInfrastructureUserMessage;
use App\Domains\Tenancy\Support\TenantStorageDisks;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TenantInfrastructureService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private ExternalTenantDatabaseService $externalDatabase,
        private ExternalTenantStorageService $storageService,
        private ExternalTenantStorageTester $externalStorage,
        private TenantByoEligibilityService $eligibility,
        private TenantInfrastructureMetricsService $metrics,
        private TenantInfrastructureAlertService $alerts,
        private TenantInfrastructureBackupService $backupManagement,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function isByoEnabled(): bool
    {
        return (bool) config('tenant_infrastructure.enabled');
    }

    public function snapshot(Tenant $tenant, bool $tenantSelfService = false): array
    {
        $record = $this->infrastructure->findForTenant($tenant->id);
        $includeLegacyAllowlist = ! $tenantSelfService;

        if ($record === null) {
            return $this->defaultSnapshot($tenant, $includeLegacyAllowlist);
        }

        if ($tenantSelfService) {
            $record = $this->reconcileSelfServiceState($tenant, $record);
        }

        return array_merge($this->eligibility->assess($tenant, $includeLegacyAllowlist), [
            'enabled' => $this->isByoEnabled(),
            'database_mode' => $record->database_mode,
            'database_config' => $this->maskDatabaseConfig($record->database_config),
            'storage_mode' => $record->storage_mode,
            'storage_config' => $this->maskStorageConfig($record->storage_config),
            'status' => $record->status,
            'status_message' => $tenantSelfService
                ? TenantInfrastructureUserMessage::sanitize($record->status_message)
                : $record->status_message,
            'health_failure_count' => (int) $record->health_failure_count,
            'last_verified_at' => $record->last_verified_at?->toIso8601String(),
            'database_migration_status' => $record->database_migration_status,
            'storage_migration_status' => $record->storage_migration_status,
            'backup_export_status' => $record->backup_export_status,
            'backup_export_path' => $record->backup_export_path,
            'backup_export_message' => $tenantSelfService
                ? TenantInfrastructureUserMessage::sanitize($record->backup_export_message)
                : $record->backup_export_message,
            'backups' => $this->backupManagement->snapshotForTenant($tenant),
            'egress_ips' => config('tenant_infrastructure.egress_ips', []),
        ]);
    }

    public function update(Tenant $tenant, array $data, bool $tenantSelfService = false): array
    {
        $record = $this->infrastructure->findForTenantOrNew($tenant);

        if ($tenantSelfService) {
            $data = $this->sanitizeSelfServiceUpdate($tenant, $data, $record);
        }

        $this->assertByoAllowed($tenant, $data, $tenantSelfService);

        $beforeModes = [
            'database_mode' => $record->exists ? $record->database_mode : TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => $record->exists ? $record->storage_mode : TenantInfrastructure::MODE_MANAGED,
        ];
        $previousDatabaseMode = $beforeModes['database_mode'];
        $previousStorageMode = $beforeModes['storage_mode'];

        $targetDatabaseExternal = ($data['database_mode'] ?? TenantInfrastructure::MODE_MANAGED) === TenantInfrastructure::MODE_EXTERNAL;
        $targetStorageExternal = ($data['storage_mode'] ?? TenantInfrastructure::MODE_MANAGED) === TenantInfrastructure::MODE_EXTERNAL;

        $needsDbMigration = $previousDatabaseMode === TenantInfrastructure::MODE_MANAGED
            && $targetDatabaseExternal
            && ($data['confirm_external_database'] ?? false)
            && $this->managedTenantDatabaseExists($tenant);

        $needsStorageMigration = $previousStorageMode === TenantInfrastructure::MODE_MANAGED
            && $targetStorageExternal
            && ($data['confirm_external_storage'] ?? false)
            && $this->managedTenantStorageHasFiles($tenant);

        if (
            $previousDatabaseMode === TenantInfrastructure::MODE_MANAGED
            && $targetDatabaseExternal
            && ! ($data['confirm_external_database'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'confirm_external_database' => 'Switching from managed to an external database requires explicit confirmation and a separate data migration.',
            ]);
        }

        if (
            $previousStorageMode === TenantInfrastructure::MODE_MANAGED
            && $targetStorageExternal
            && ! ($data['confirm_external_storage'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'confirm_external_storage' => 'Switching from managed to external storage requires explicit confirmation and a separate file migration.',
            ]);
        }

        if (config('tenant_infrastructure.test_on_save', true)) {
            if ($targetDatabaseExternal || $needsDbMigration) {
                $this->testDatabaseCredentials($tenant, $data['database_config'] ?? []);
            }

            if ($targetStorageExternal || $needsStorageMigration) {
                $this->testStorageCredentials($tenant, $data['storage_config'] ?? []);
            }
        }

        if ($needsDbMigration) {
            $record->database_config = $this->mergeDatabaseConfig(
                $record->database_config,
                $data['database_config'] ?? [],
            );
            $record->database_mode = TenantInfrastructure::MODE_MANAGED;
            $record->database_migration_status = TenantInfrastructure::MIGRATION_QUEUED;
        } elseif ($targetDatabaseExternal) {
            $record->database_mode = TenantInfrastructure::MODE_EXTERNAL;
            $record->database_config = $this->mergeDatabaseConfig(
                $record->database_config,
                $data['database_config'] ?? [],
            );
            $record->database_migration_status = null;
        } else {
            $record->database_mode = TenantInfrastructure::MODE_MANAGED;
            $record->database_config = null;
            $record->database_migration_status = null;
        }

        if ($needsStorageMigration) {
            $record->storage_config = $this->mergeStorageConfig(
                $record->storage_config,
                $data['storage_config'] ?? [],
                $tenant,
            );
            $record->storage_mode = TenantInfrastructure::MODE_MANAGED;
            $record->storage_migration_status = TenantInfrastructure::MIGRATION_QUEUED;
        } elseif ($targetStorageExternal) {
            $record->storage_mode = TenantInfrastructure::MODE_EXTERNAL;
            $record->storage_config = $this->mergeStorageConfig(
                $record->storage_config,
                $data['storage_config'] ?? [],
                $tenant,
            );
            $record->storage_migration_status = null;
        } else {
            $record->storage_mode = TenantInfrastructure::MODE_MANAGED;
            $record->storage_config = null;
            $record->storage_migration_status = null;
        }

        $record->status = TenantInfrastructure::STATUS_PENDING;
        $record->status_message = $needsDbMigration || $needsStorageMigration
            ? 'Infrastructure migration queued.'
            : null;
        $record->health_failure_count = 0;

        $saved = $this->infrastructure->save($record);

        if ($needsDbMigration) {
            MigrateManagedToExternalDatabaseJob::dispatch($tenant->id);
        } elseif ($saved->usesExternalDatabase()) {
            $this->externalDatabase->applyToTenant($tenant, $saved);
        }

        if ($needsStorageMigration) {
            MigrateManagedToExternalStorageJob::dispatch($tenant->id);
        } elseif (! $needsDbMigration) {
            $this->queueVerificationIfExternal($saved);
        }

        $this->audit->record(
            'platform.tenant.infrastructure_updated',
            $tenant,
            [
                'database_mode' => $saved->database_mode,
                'storage_mode' => $saved->storage_mode,
                'previous_database_mode' => $beforeModes['database_mode'],
                'previous_storage_mode' => $beforeModes['storage_mode'],
            ],
            tenantId: $tenant->id,
        );

        return $this->snapshot($tenant->fresh());
    }

    public function testDatabaseCredentials(Tenant $tenant, array $incomingConfig): void
    {
        $record = $this->infrastructure->findForTenant($tenant->id);
        $merged = $this->mergeDatabaseConfig($record?->database_config, $incomingConfig);

        $databaseError = $this->externalDatabase->testConnection($merged);

        if ($databaseError !== null) {
            throw ValidationException::withMessages([
                'database_config' => $databaseError,
            ]);
        }

        $readOnlyError = $this->externalDatabase->testReadOnlyConnection($merged);

        if ($readOnlyError !== null) {
            throw ValidationException::withMessages([
                'database_config' => $readOnlyError,
            ]);
        }
    }

    public function testStorageCredentials(Tenant $tenant, array $incomingConfig): void
    {
        $record = $this->infrastructure->findForTenant($tenant->id);
        $merged = $this->mergeStorageConfig($record?->storage_config, $incomingConfig, $tenant);

        $probe = new TenantInfrastructure([
            'tenant_id' => $tenant->id,
            'storage_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_config' => $merged,
        ]);

        $storageError = $this->externalStorage->test($probe);

        if ($storageError !== null) {
            throw ValidationException::withMessages([
                'storage_config' => $storageError,
            ]);
        }
    }

    public function verify(Tenant $tenant): array
    {
        $record = $this->infrastructure->findForTenant($tenant->id);

        if ($record === null) {
            throw new InvalidArgumentException('Infrastructure is not configured for this workspace.');
        }

        $errors = [];

        if ($record->hasDatabaseTargetConfig()) {
            $databaseError = $this->externalDatabase->testConnection($record->database_config ?? []);

            if ($databaseError !== null) {
                $errors[] = $databaseError;
            } else {
                $readOnlyError = $this->externalDatabase->testReadOnlyConnection($record->database_config ?? []);

                if ($readOnlyError !== null) {
                    $errors[] = $readOnlyError;
                } elseif ($record->usesExternalDatabase()) {
                    $this->externalDatabase->applyToTenant($tenant, $record);
                    $record = $record->fresh();

                    if ($this->externalDatabase->shouldRunTenantMigrations($tenant->fresh(), $record)) {
                        $migrationError = $this->externalDatabase->migrate($tenant);

                        if ($migrationError !== null) {
                            $errors[] = $migrationError;
                        }
                    } elseif ($record->database_migration_status !== TenantInfrastructure::MIGRATION_COMPLETED) {
                        $record->database_migration_status = TenantInfrastructure::MIGRATION_COMPLETED;
                        $this->infrastructure->save($record);
                    }
                }
            }
        }

        if ($record->hasStorageTargetConfig()) {
            $storageError = $this->externalStorage->test($record);

            if ($storageError !== null) {
                $errors[] = $storageError;
            }
        }

        if ($errors !== []) {
            $message = implode(' ', $errors);
            $this->infrastructure->markStatus($record, TenantInfrastructure::STATUS_FAILED, $message);
            $this->metrics->incrementVerifyFailures();

            $this->audit->record(
                'platform.tenant.infrastructure_failed',
                $tenant,
                [
                    'message' => $message,
                    'source' => 'verify',
                ],
                tenantId: $tenant->id,
            );

            $this->alerts->notifyFailure($record->fresh(['tenant']), $message, 'verify');

            throw ValidationException::withMessages([
                'infrastructure' => $message,
            ]);
        }

        $this->infrastructure->markStatus(
            $record,
            TenantInfrastructure::STATUS_VERIFIED,
            null,
            true,
        );

        $this->audit->record(
            'platform.tenant.infrastructure_verified',
            $tenant,
            [
                'database_mode' => $record->database_mode,
                'storage_mode' => $record->storage_mode,
                'source' => 'verify',
            ],
            tenantId: $tenant->id,
        );

        return $this->snapshot($tenant->fresh());
    }

    public function resolveForTenant(Tenant $tenant): ?TenantInfrastructure
    {
        return $this->infrastructure->findForTenant($tenant->id);
    }

    public function usesExternalDatabase(Tenant $tenant): bool
    {
        $record = $this->resolveForTenant($tenant);

        return $record !== null && $record->usesExternalDatabase();
    }

    public function usesExternalStorage(Tenant $tenant): bool
    {
        $record = $this->resolveForTenant($tenant);

        return $record !== null && $record->usesExternalStorage();
    }

    private function reconcileSelfServiceState(Tenant $tenant, TenantInfrastructure $record): TenantInfrastructure
    {
        $changed = false;

        if ($this->reconcileHealthyExternalDatabase($tenant, $record)) {
            $changed = true;
        }

        if (
            ! $this->eligibility->canConfigureDatabase($tenant, false)
            && $record->database_mode === TenantInfrastructure::MODE_MANAGED
            && ($record->hasPendingDatabaseMigration() || $record->hasDatabaseTargetConfig())
        ) {
            $record->database_migration_status = null;
            $record->database_config = null;

            if ($this->isDatabaseMigrationStatusMessage($record->status_message)) {
                $record->status_message = null;

                if (
                    $record->status === TenantInfrastructure::STATUS_FAILED
                    && ! $record->hasPendingStorageMigration()
                ) {
                    $record->status = TenantInfrastructure::STATUS_PENDING;
                }
            }

            $changed = true;
        }

        if (
            $this->eligibility->canConfigureDatabase($tenant, false)
            && $record->database_migration_status === TenantInfrastructure::MIGRATION_RUNNING
            && $record->updated_at !== null
            && $record->updated_at->lt(now()->subHours(2))
        ) {
            $record->database_migration_status = TenantInfrastructure::MIGRATION_FAILED;
            $record->status = TenantInfrastructure::STATUS_FAILED;
            $record->status_message = 'Database migration timed out. Retry after confirming your credentials.';
            $changed = true;
        }

        if (
            $this->eligibility->canConfigureStorage($tenant, false)
            && $record->storage_migration_status === TenantInfrastructure::MIGRATION_RUNNING
            && $record->updated_at !== null
            && $record->updated_at->lt(now()->subHours(2))
        ) {
            $record->storage_migration_status = TenantInfrastructure::MIGRATION_FAILED;
            $record->status = TenantInfrastructure::STATUS_FAILED;
            $record->status_message = 'Storage migration timed out. Retry after confirming your credentials.';
            $changed = true;
        }

        if (! $changed) {
            return $record;
        }

        $this->infrastructure->save($record);

        return $record->fresh();
    }

    private function reconcileHealthyExternalDatabase(Tenant $tenant, TenantInfrastructure $record): bool
    {
        if (
            $record->status !== TenantInfrastructure::STATUS_FAILED
            || ! $record->usesExternalDatabase()
            || ! $this->isTenantMigrationFailureMessage($record->status_message)
        ) {
            return false;
        }

        if ($this->externalDatabase->testConnection($record->database_config ?? []) !== null) {
            return false;
        }

        $this->externalDatabase->applyToTenant($tenant, $record);

        if (! $this->externalDatabase->tenantSchemaIsInitialized($tenant->fresh())) {
            return false;
        }

        if ($record->hasStorageTargetConfig() && $record->usesExternalStorage()) {
            $storageError = $this->externalStorage->test($record);

            if ($storageError !== null) {
                return false;
            }
        }

        $record->status = TenantInfrastructure::STATUS_VERIFIED;
        $record->status_message = null;
        $record->database_migration_status = TenantInfrastructure::MIGRATION_COMPLETED;
        $record->last_verified_at = now();

        return true;
    }

    private function isTenantMigrationFailureMessage(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        $lower = strtolower($message);

        return str_contains($lower, 'tenant migration failed')
            || str_contains($lower, 'database migration failed')
            || str_contains($lower, 'sqlstate');
    }

    private function isDatabaseMigrationStatusMessage(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        return str_contains(strtolower($message), 'database migration');
    }

    private function queueVerificationIfExternal(TenantInfrastructure $record): void
    {
        if ($record->hasPendingDatabaseMigration() || $record->hasPendingStorageMigration()) {
            return;
        }

        if (
            ! $record->usesExternalDatabase()
            && ! $record->usesExternalStorage()
            && ! $record->hasDatabaseTargetConfig()
            && ! $record->hasStorageTargetConfig()
        ) {
            return;
        }

        VerifyTenantInfrastructureJob::dispatch($record->tenant_id);
    }

    private function defaultSnapshot(Tenant $tenant, bool $includeLegacyAllowlist = true): array
    {
        return array_merge($this->eligibility->assess($tenant, $includeLegacyAllowlist), [
            'enabled' => $this->isByoEnabled(),
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => null,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_config' => null,
            'status' => TenantInfrastructure::STATUS_VERIFIED,
            'status_message' => null,
            'health_failure_count' => 0,
            'last_verified_at' => null,
            'database_migration_status' => null,
            'storage_migration_status' => null,
            'backup_export_status' => null,
            'backup_export_path' => null,
            'backup_export_message' => null,
            'backups' => null,
            'egress_ips' => config('tenant_infrastructure.egress_ips', []),
        ]);
    }

    private function assertByoAllowed(Tenant $tenant, array $data, bool $tenantSelfService = false): void
    {
        $includeLegacyAllowlist = ! $tenantSelfService;
        $targetDatabaseExternal = ($data['database_mode'] ?? TenantInfrastructure::MODE_MANAGED) === TenantInfrastructure::MODE_EXTERNAL;
        $targetStorageExternal = ($data['storage_mode'] ?? TenantInfrastructure::MODE_MANAGED) === TenantInfrastructure::MODE_EXTERNAL;

        if ($targetDatabaseExternal) {
            $this->eligibility->assertCanConfigureDatabase($tenant, $includeLegacyAllowlist);
        }

        if ($targetStorageExternal) {
            $this->eligibility->assertCanConfigureStorage($tenant, $includeLegacyAllowlist);
        }
    }

    private function sanitizeSelfServiceUpdate(Tenant $tenant, array $data, TenantInfrastructure $record): array
    {
        if (! $this->eligibility->canConfigureDatabase($tenant, false)) {
            $data['database_mode'] = $record->exists
                ? $record->database_mode
                : TenantInfrastructure::MODE_MANAGED;
            unset($data['database_config'], $data['confirm_external_database']);
        }

        if (! $this->eligibility->canConfigureStorage($tenant, false)) {
            $data['storage_mode'] = $record->exists
                ? $record->storage_mode
                : TenantInfrastructure::MODE_MANAGED;
            unset($data['storage_config'], $data['confirm_external_storage']);
        }

        return $data;
    }

    private function mergeDatabaseConfig(?array $existing, array $incoming): array
    {
        $merged = array_merge($existing ?? [], Arr::only($incoming, [
            'host',
            'port',
            'database',
            'username',
            'password',
            'read_only_username',
            'read_only_password',
            'ssl',
            'read_replica_host',
        ]));

        if (filled($merged['read_replica_host'] ?? null)) {
            throw ValidationException::withMessages([
                'database_config.read_replica_host' => 'Read replica hosts are not supported. Provide the primary database host only.',
            ]);
        }

        unset($merged['read_replica_host']);

        if (($incoming['password'] ?? '') === '' && isset($existing['password'])) {
            $merged['password'] = $existing['password'];
        }

        if (($incoming['read_only_password'] ?? '') === '' && isset($existing['read_only_password'])) {
            $merged['read_only_password'] = $existing['read_only_password'];
        }

        if (! filled($merged['read_only_username'] ?? null)) {
            unset($merged['read_only_username'], $merged['read_only_password']);
        } elseif (! filled($merged['read_only_password'] ?? null)) {
            throw ValidationException::withMessages([
                'database_config.read_only_password' => 'Read-only password is required when a read-only username is set.',
            ]);
        }

        $merged['port'] = (int) ($merged['port'] ?? 3306);
        $merged['ssl'] = (bool) ($merged['ssl'] ?? false);

        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (! filled($merged[$key] ?? null)) {
                throw ValidationException::withMessages([
                    "database_config.{$key}" => 'This field is required for an external database.',
                ]);
            }
        }

        $databaseName = strtolower((string) $merged['database']);

        if (in_array($databaseName, config('tenant_infrastructure.reserved_mysql_databases', []), true)) {
            throw ValidationException::withMessages([
                'database_config.database' => 'Use a dedicated application database (for example helpefi_workspace), not a MySQL system database.',
            ]);
        }

        return $merged;
    }

    private function mergeStorageConfig(?array $existing, array $incoming, Tenant $tenant): array
    {
        $merged = array_merge($existing ?? [], Arr::only($incoming, [
            'driver',
            'bucket',
            'region',
            'endpoint',
            'access_key_id',
            'secret_access_key',
            'prefix',
            'cdn_url',
        ]));

        if (($incoming['secret_access_key'] ?? '') === '' && isset($existing['secret_access_key'])) {
            $merged['secret_access_key'] = $existing['secret_access_key'];
        }

        $driver = $merged['driver'] ?? 's3';

        if (! in_array($driver, config('tenant_infrastructure.allowed_storage_drivers', []), true)) {
            throw ValidationException::withMessages([
                'storage_config.driver' => 'Unsupported storage driver.',
            ]);
        }

        foreach (['bucket', 'access_key_id', 'secret_access_key'] as $key) {
            if (! filled($merged[$key] ?? null)) {
                throw ValidationException::withMessages([
                    "storage_config.{$key}" => 'This field is required for external storage.',
                ]);
            }
        }

        if ($driver === 'r2' && ! filled($merged['endpoint'] ?? null)) {
            throw ValidationException::withMessages([
                'storage_config.endpoint' => 'Endpoint is required for Cloudflare R2.',
            ]);
        }

        if ($driver === 'r2') {
            try {
                $merged['region'] = $this->storageService->resolveR2Region($merged['region'] ?? null);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'storage_config.region' => $exception->getMessage(),
                ]);
            }
        } elseif (! filled($merged['region'] ?? null)) {
            throw ValidationException::withMessages([
                'storage_config.region' => 'Region is required for AWS S3.',
            ]);
        }

        if (! filled($merged['prefix'] ?? null)) {
            $merged['prefix'] = trim(config('tenant_infrastructure.storage_key_prefix', 'helpefi'), '/')
                .'/'
                .$tenant->id;
        }

        return $merged;
    }

    private function maskDatabaseConfig(?array $config): ?array
    {
        if ($config === null) {
            return null;
        }

        if (isset($config['password'])) {
            $config['password'] = $this->maskSecret((string) $config['password']);
        }

        if (isset($config['read_only_password'])) {
            $config['read_only_password'] = $this->maskSecret((string) $config['read_only_password']);
        }

        return $config;
    }

    private function maskStorageConfig(?array $config): ?array
    {
        if ($config === null) {
            return null;
        }

        if (isset($config['secret_access_key'])) {
            $config['secret_access_key'] = $this->maskSecret((string) $config['secret_access_key']);
        }

        return $config;
    }

    private function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_repeat('•', min(12, max(8, strlen($value))));
    }

    private function managedTenantDatabaseExists(Tenant $tenant): bool
    {
        if ($this->usesExternalDatabase($tenant)) {
            return false;
        }

        try {
            $exists = false;

            $tenant->run(function () use (&$exists) {
                $driver = DB::connection()->getDriverName();

                if ($driver === 'sqlite') {
                    $exists = count(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")) > 0;

                    return;
                }

                $exists = count(DB::select('SHOW TABLES')) > 0;
            });

            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }

    private function managedTenantStorageHasFiles(Tenant $tenant): bool
    {
        try {
            $hasFiles = false;

            $tenant->run(function () use (&$hasFiles) {
                if (\App\Domains\Tickets\Models\TicketAttachment::query()->whereNotNull('path')->exists()) {
                    $hasFiles = true;

                    return;
                }

                if (\App\Models\User::query()->whereNotNull('avatar_path')->exists()) {
                    $hasFiles = true;

                    return;
                }

                $hasFiles = count(Storage::disk(TenantStorageDisks::MANAGED)->allFiles()) > 0;
            });

            return $hasFiles;
        } catch (\Throwable) {
            return false;
        }
    }
}
