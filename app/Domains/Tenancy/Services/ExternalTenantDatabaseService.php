<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class ExternalTenantDatabaseService
{
    public function applyToTenant(Tenant $tenant, TenantInfrastructure $infrastructure): void
    {
        if (! $infrastructure->usesExternalDatabase()) {
            return;
        }

        $config = $infrastructure->database_config ?? [];

        $tenant->setInternal('create_database', false);
        $tenant->setInternal('db_name', $config['database'] ?? null);
        $tenant->setInternal('db_host', $config['host'] ?? null);
        $tenant->setInternal('db_port', (string) ($config['port'] ?? 3306));
        $tenant->setInternal('db_username', $config['username'] ?? null);
        $tenant->setInternal('db_password', $config['password'] ?? null);
        $tenant->save();
    }

    public function testConnection(array $config): ?string
    {
        return app(ExternalTenantDatabaseTester::class)->testConnection($config);
    }

    public function testReadOnlyConnection(array $config): ?string
    {
        return app(ExternalTenantDatabaseTester::class)->testReadOnlyConnection($config);
    }

    public function shouldRunTenantMigrations(Tenant $tenant, TenantInfrastructure $infrastructure): bool
    {
        if ($infrastructure->database_migration_status === TenantInfrastructure::MIGRATION_COMPLETED) {
            return false;
        }

        if (! $infrastructure->usesExternalDatabase()) {
            return true;
        }

        return ! $this->tenantSchemaIsInitialized($tenant->fresh());
    }

    public function migrate(Tenant $tenant): ?string
    {
        try {
            $tenant = $tenant->fresh();

            if (! filled($tenant->getInternal('db_name')) || ! filled($tenant->getInternal('db_host'))) {
                return 'Tenant database credentials are not configured.';
            }

            if ($this->tenantSchemaIsInitialized($tenant)) {
                return null;
            }

            $tenant->run(function () use ($tenant) {
                /** @var Migrator $migrator */
                $migrator = app(Migrator::class);

                $migrator->usingConnection('tenant', function () use ($migrator) {
                    $migrator->setOutput(new BufferedOutput());
                    $migrator->run([database_path('migrations/tenant')], [
                        'pretend' => false,
                        'step' => false,
                    ]);
                });
            });

            return null;
        } catch (Throwable $exception) {
            $message = 'Tenant migration failed: '.$exception->getMessage();
            $this->notifyMigrationFailure($tenant, $message);

            return $message;
        }
    }

    public function tenantSchemaIsInitialized(Tenant $tenant): bool
    {
        $initialized = false;

        $tenant->run(function () use (&$initialized) {
            $initialized = $this->schemaIsInitializedOnConnection('tenant');
        });

        return $initialized;
    }

    private function schemaIsInitializedOnConnection(string $connection): bool
    {
        try {
            if (Schema::connection($connection)->hasTable('migrations')) {
                return DB::connection($connection)->table('migrations')->exists();
            }

            return Schema::connection($connection)->hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    private function notifyMigrationFailure(Tenant $tenant, string $message): void
    {
        $record = app(TenantInfrastructureRepository::class)->findForTenant($tenant->id);

        if ($record === null) {
            return;
        }

        app(TenantInfrastructureAlertService::class)->notifyFailure(
            $record->fresh(['tenant']),
            $message,
            'migration',
        );

        app(PlatformAuditRecorder::class)->record(
            'platform.tenant.infrastructure_migration_failed',
            $tenant,
            [
                'message' => $message,
                'source' => 'migration',
            ],
            tenantId: $tenant->id,
        );
    }

    public function buildConnectionConfig(array $config): array
    {
        $template = config('database.connections.'.config('tenancy.database.central_connection', 'central'));

        $connection = array_merge($template, [
            'driver' => 'mysql',
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (string) ($config['port'] ?? 3306),
            'database' => $config['database'] ?? '',
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
        ]);

        $timeout = max(1, (int) config('tenant_infrastructure.connection_timeout_seconds', 10));

        $options = [
            \PDO::ATTR_TIMEOUT => $timeout,
        ];

        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $options[constant('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')] = $timeout;
        }

        $connection['options'] = $this->mergePdoOptions($connection['options'] ?? [], $options);

        if (($config['ssl'] ?? false) === true) {
            $connection['options'] = $this->mergePdoOptions($connection['options'] ?? [], [
                \PDO::MYSQL_ATTR_SSL_CA => $config['ssl_ca'] ?? '',
                \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ]);
        }

        return $connection;
    }

    private function mergePdoOptions(array $existing, array $incoming): array
    {
        return $existing + $incoming;
    }
}
