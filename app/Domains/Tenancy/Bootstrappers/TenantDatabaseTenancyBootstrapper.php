<?php

namespace App\Domains\Tenancy\Bootstrappers;

use App\Domains\Tenancy\Services\ExternalTenantDatabaseService;
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
        private ExternalTenantDatabaseService $externalDatabase,
    ) {
    }

    public function bootstrap(Tenant $tenant): void
    {
        /** @var TenantWithDatabase $tenant */
        if ($this->usesExternalDatabaseHost($tenant)) {
            $this->connectExternalTenant($tenant);

            return;
        }

        if (app()->environment('local')) {
            $this->assertDatabaseExists($tenant);
        }

        $this->database->connectToTenant($tenant);
    }

    public function revert(): void
    {
        $this->database->reconnectToCentral();
    }

    private function connectExternalTenant(TenantWithDatabase $tenant): void
    {
        $this->database->purgeTenantConnection();

        $connection = $this->externalDatabase->enhanceConnectionConfig(
            $tenant->database()->connection()
        );

        Config::set('database.connections.tenant', $connection);
        $this->database->setDefaultConnection('tenant');
        DB::purge('tenant');
        DB::connection('tenant');
    }

    private function assertDatabaseExists(TenantWithDatabase $tenant): void
    {
        $database = $tenant->database()->getName();

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
}
