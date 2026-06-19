<?php

namespace App\Domains\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

class TenantDatabaseTenancyBootstrapper implements TenancyBootstrapper
{
    public function __construct(
        private DatabaseManager $database,
    ) {
    }

    public function bootstrap(Tenant $tenant): void
    {
        /** @var TenantWithDatabase $tenant */

        if (app()->environment('local')) {
            $this->assertDatabaseExists($tenant);
        }

        $this->database->connectToTenant($tenant);
    }

    public function revert(): void
    {
        $this->database->reconnectToCentral();
    }

    private function assertDatabaseExists(TenantWithDatabase $tenant): void
    {
        $database = $tenant->database()->getName();

        if ($this->usesExternalDatabaseHost($tenant)) {
            $this->assertExternalDatabaseExists($tenant, $database);

            return;
        }

        if (! $tenant->database()->manager()->databaseExists($database)) {
            throw new TenantDatabaseDoesNotExistException($database);
        }
    }

    private function usesExternalDatabaseHost(TenantWithDatabase $tenant): bool
    {
        if ($tenant->getInternal('create_database') === false) {
            return true;
        }

        $tenantHost = $tenant->getInternal('db_host');

        if (! filled($tenantHost)) {
            return false;
        }

        $centralHost = config('database.connections.'.config('tenancy.database.central_connection').'.host');

        return (string) $tenantHost !== (string) $centralHost;
    }

    private function assertExternalDatabaseExists(TenantWithDatabase $tenant, string $database): void
    {
        $connectionName = 'tenant_bootstrap_check';

        Config::set("database.connections.{$connectionName}", $tenant->database()->connection());

        try {
            DB::purge($connectionName);

            $exists = DB::connection($connectionName)->select(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$database],
            );

            if ($exists === []) {
                throw new TenantDatabaseDoesNotExistException($database);
            }
        } finally {
            DB::purge($connectionName);
            Config::offsetUnset("database.connections.{$connectionName}");
        }
    }
}
