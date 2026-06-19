<?php

namespace Tests\Unit;

use App\Domains\Tenancy\Bootstrappers\TenantDatabaseTenancyBootstrapper;
use App\Models\Tenant;
use ReflectionMethod;
use Tests\TestCase;

class TenantDatabaseTenancyBootstrapperTest extends TestCase
{
    public function test_detects_external_database_from_create_database_flag(): void
    {
        $tenant = new Tenant(['id' => 'external-tenant']);
        $tenant->setInternal('create_database', false);

        $method = new ReflectionMethod(TenantDatabaseTenancyBootstrapper::class, 'usesExternalDatabaseHost');
        $method->setAccessible(true);

        $bootstrapper = new TenantDatabaseTenancyBootstrapper(
            $this->createMock(\Stancl\Tenancy\Database\DatabaseManager::class),
        );

        $this->assertTrue($method->invoke($bootstrapper, $tenant));
    }

    public function test_detects_external_database_from_custom_host(): void
    {
        config([
            'tenancy.database.central_connection' => 'central',
            'database.connections.central.host' => 'mysql',
        ]);

        $tenant = new Tenant(['id' => 'external-host-tenant']);
        $tenant->setInternal('db_host', 'database-1.example.rds.amazonaws.com');

        $method = new ReflectionMethod(TenantDatabaseTenancyBootstrapper::class, 'usesExternalDatabaseHost');
        $method->setAccessible(true);

        $bootstrapper = new TenantDatabaseTenancyBootstrapper(
            $this->createMock(\Stancl\Tenancy\Database\DatabaseManager::class),
        );

        $this->assertTrue($method->invoke($bootstrapper, $tenant));
    }
}
