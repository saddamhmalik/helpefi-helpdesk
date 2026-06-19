<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\ExternalTenantDatabaseTester;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantExternalDatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->integrationEnabled()) {
            $this->markTestSkipped('Set BYO_INTEGRATION_MYSQL_HOST to run live external database integration tests.');
        }

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
        config(['tenant_infrastructure.enabled' => true]);
    }

    public function test_external_mysql_connection_and_verify(): void
    {
        $tenant = Tenant::create([
            'id' => 'integration-external',
            'name' => 'Integration External',
            'slug' => 'integration-external',
            'byo_allowed' => true,
        ]);

        $config = [
            'host' => (string) env('BYO_INTEGRATION_MYSQL_HOST'),
            'port' => (int) env('BYO_INTEGRATION_MYSQL_PORT', 3306),
            'database' => (string) env('BYO_INTEGRATION_MYSQL_DATABASE', 'helpdesk_external_test'),
            'username' => (string) env('BYO_INTEGRATION_MYSQL_USERNAME', 'root'),
            'password' => (string) env('BYO_INTEGRATION_MYSQL_PASSWORD', ''),
        ];

        $this->assertNull(app(ExternalTenantDatabaseTester::class)->testConnection($config));

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => $config,
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        app(TenantInfrastructureService::class)->verify($tenant);

        $this->assertSame(
            TenantInfrastructure::STATUS_VERIFIED,
            TenantInfrastructure::query()->where('tenant_id', $tenant->id)->value('status'),
        );

        $tenant->delete();
    }

    private function integrationEnabled(): bool
    {
        return filled(env('BYO_INTEGRATION_MYSQL_HOST'));
    }
}
