<?php

namespace Tests\Feature;

use App\Domains\Platform\Services\PlatformBackupService;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\ExternalTenantDatabaseService;
use App\Domains\Tenancy\Services\TenantInfrastructureHealthService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantInfrastructureHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
        config(['tenant_infrastructure.health_failure_threshold' => 3]);
    }

    public function test_health_check_marks_failed_after_consecutive_errors(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = Tenant::create([
            'id' => 'health-fail',
            'name' => 'Health Fail',
            'slug' => 'health-fail',
        ]);

        $infrastructure = TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'database_config' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'missing',
                'username' => 'bad',
                'password' => 'bad',
            ],
            'status' => TenantInfrastructure::STATUS_VERIFIED,
            'last_verified_at' => now(),
        ]);

        $database = $this->createMock(ExternalTenantDatabaseService::class);
        $database->method('testConnection')->willReturn('Database connection failed.');
        $this->app->instance(ExternalTenantDatabaseService::class, $database);

        $health = app(TenantInfrastructureHealthService::class);

        $health->check($infrastructure->fresh());
        $health->check($infrastructure->fresh());
        $this->assertSame(TenantInfrastructure::STATUS_VERIFIED, $infrastructure->fresh()->status);
        $this->assertSame(2, $infrastructure->fresh()->health_failure_count);

        $health->check($infrastructure->fresh());

        $this->app->terminate();

        $this->assertSame(TenantInfrastructure::STATUS_FAILED, $infrastructure->fresh()->status);
        $this->assertDatabaseHas('platform_audit_logs', [
            'event' => 'platform.tenant.infrastructure_failed',
        ], 'central');

        $tenant->delete();
    }

    public function test_backup_is_skipped_for_external_database_tenant(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = Tenant::create([
            'id' => 'backup-skip',
            'name' => 'Backup Skip',
            'slug' => 'backup-skip',
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

        $actor = \App\Models\PlatformUser::query()->where('email', PlatformUserSeeder::DEFAULT_EMAIL)->firstOrFail();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PlatformBackupService::class)->queueTenant($tenant->id, $actor);

        $tenant->delete();
    }

    public function test_infrastructure_update_writes_audit_log(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = Tenant::create([
            'id' => 'audit-update',
            'name' => 'Audit Update',
            'slug' => 'audit-update',
        ]);

        app(TenantInfrastructureService::class)->update($tenant, [
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
        ]);

        $this->app->terminate();

        $this->assertDatabaseHas('platform_audit_logs', [
            'event' => 'platform.tenant.infrastructure_updated',
            'tenant_id' => $tenant->id,
        ], 'central');

        $tenant->delete();
    }
}
