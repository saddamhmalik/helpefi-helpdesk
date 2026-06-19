<?php

namespace Tests\Unit;

use App\Domains\Tenancy\Bootstrappers\TenantDatabaseTenancyBootstrapper;
use App\Domains\Tenancy\Services\ExternalTenantDatabaseService;
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
            app(ExternalTenantDatabaseService::class),
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
            app(ExternalTenantDatabaseService::class),
        );

        $this->assertTrue($method->invoke($bootstrapper, $tenant));
    }

    public function test_external_connection_config_enables_compression_by_default(): void
    {
        config(['tenant_infrastructure.compress_connections' => true]);

        $connection = app(ExternalTenantDatabaseService::class)->enhanceConnectionConfig([
            'driver' => 'mysql',
            'host' => 'db.example.com',
            'database' => 'helpdesk',
            'username' => 'app',
            'password' => 'secret',
        ]);

        if (defined('PDO::MYSQL_ATTR_COMPRESS')) {
            $this->assertSame(true, $connection['options'][constant('PDO::MYSQL_ATTR_COMPRESS')]);
        } else {
            $this->assertArrayNotHasKey('compress', $connection);
        }
    }
}
