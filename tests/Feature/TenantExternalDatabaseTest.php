<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Jobs\CreateTenantDatabaseJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\ExternalTenantDatabaseService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Stancl\Tenancy\Database\DatabaseManager;
use Tests\TestCase;

class TenantExternalDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
        config(['tenant_infrastructure.enabled' => true]);
        Queue::fake();
    }

    public function test_create_tenant_database_job_skips_create_for_external_tenant(): void
    {
        $tenant = Tenant::create([
            'id' => 'external-create-skip',
            'name' => 'External Create Skip',
            'slug' => 'external-create-skip',
        ]);

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
            'status' => TenantInfrastructure::STATUS_VERIFIED,
        ]);

        $databaseManager = $this->createMock(DatabaseManager::class);
        $databaseManager->expects($this->never())->method('ensureTenantCanBeCreated');

        (new CreateTenantDatabaseJob($tenant))->handle(
            $databaseManager,
            app(TenantInfrastructureService::class),
        );

        $this->assertFalse((bool) $tenant->fresh()->getInternal('create_database'));

        $tenant->delete();
    }

    public function test_verify_failure_sets_failed_status(): void
    {
        $tenant = $this->eligibleTenant('verify-fail', 'verify-fail');

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => 'invalid.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        try {
            app(TenantInfrastructureService::class)->verify($tenant);
        } catch (\Illuminate\Validation\ValidationException) {
        }

        $record = TenantInfrastructure::query()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(TenantInfrastructure::STATUS_FAILED, $record->status);
        $this->assertNotNull($record->status_message);

        $tenant->delete();
    }

    public function test_verify_runs_migrations_on_external_database(): void
    {
        $tenant = $this->eligibleTenant('verify-migrate', 'verify-migrate');

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        $database = $this->createMock(ExternalTenantDatabaseService::class);
        $database->method('testConnection')->willReturn(null);
        $database->expects($this->once())->method('migrate')->with($tenant)->willReturn(null);
        $database->method('applyToTenant');
        $this->app->instance(ExternalTenantDatabaseService::class, $database);

        app(TenantInfrastructureService::class)->verify($tenant);

        $record = TenantInfrastructure::query()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(TenantInfrastructure::STATUS_VERIFIED, $record->status);

        $tenant->delete();
    }

    public function test_verify_skips_migrate_after_managed_to_external_import(): void
    {
        $tenant = $this->eligibleTenant('verify-skip-migrate', 'verify-skip-migrate');

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
            ],
            'database_migration_status' => TenantInfrastructure::MIGRATION_COMPLETED,
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        $database = $this->createMock(ExternalTenantDatabaseService::class);
        $database->method('testConnection')->willReturn(null);
        $database->method('testReadOnlyConnection')->willReturn(null);
        $database->expects($this->once())->method('applyToTenant');
        $database->method('shouldRunTenantMigrations')->willReturn(false);
        $database->expects($this->never())->method('migrate');
        $this->app->instance(ExternalTenantDatabaseService::class, $database);

        app(TenantInfrastructureService::class)->verify($tenant);

        $record = TenantInfrastructure::query()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(TenantInfrastructure::STATUS_VERIFIED, $record->status);

        $tenant->delete();
    }

    public function test_connection_config_sets_mysql_connect_timeout(): void
    {
        config(['tenant_infrastructure.connection_timeout_seconds' => 8]);

        $connection = app(ExternalTenantDatabaseService::class)->buildConnectionConfig([
            'host' => 'db.example.com',
            'port' => 3306,
            'database' => 'helpdesk',
            'username' => 'app',
            'password' => 'secret',
        ]);

        $this->assertSame(8, $connection['options'][\PDO::ATTR_TIMEOUT]);

        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $this->assertSame(8, $connection['options'][constant('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')]);
        }
    }

    public function test_reserved_mysql_system_database_name_is_rejected(): void
    {
        $tenant = $this->eligibleTenant('reserved-db', 'reserved-db');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            app(TenantInfrastructureService::class)->update($tenant, [
                'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
                'storage_mode' => TenantInfrastructure::MODE_MANAGED,
                'database_config' => [
                    'host' => 'db.example.com',
                    'port' => 3306,
                    'database' => 'mysql',
                    'username' => 'app',
                    'password' => 'secret',
                ],
            ]);
        } finally {
            $tenant->delete();
        }
    }

    public function test_read_replica_host_is_rejected(): void
    {
        $tenant = $this->eligibleTenant('read-replica', 'read-replica');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TenantInfrastructureService::class)->update($tenant, [
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
                'read_replica_host' => 'replica.example.com',
            ],
        ]);

        $tenant->delete();
    }

    private function eligibleTenant(string $id, string $slug): Tenant
    {
        $tenant = Tenant::create([
            'id' => $id,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'byo_allowed' => true,
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'enterprise',
                'status' => Subscription::STATUS_ACTIVE,
                'billing_interval' => 'month',
                'renews_at' => now()->addMonth(),
                'trial_ends_at' => null,
            ],
        );

        return $tenant->fresh(['subscription']);
    }
}
