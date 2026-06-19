<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Jobs\VerifyTenantInfrastructureJob;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
        \Illuminate\Support\Facades\Queue::fake();
    }

    public function test_snapshot_defaults_to_managed_when_no_record_exists(): void
    {
        $tenant = Tenant::create([
            'id' => 'snapshot-tenant',
            'name' => 'Snapshot Tenant',
            'slug' => 'snapshot-tenant',
        ]);

        $snapshot = app(TenantInfrastructureService::class)->snapshot($tenant);

        $this->assertSame('managed', $snapshot['database_mode']);
        $this->assertSame('managed', $snapshot['storage_mode']);
        $this->assertSame('verified', $snapshot['status']);

        $tenant->delete();
    }

    public function test_external_database_config_is_rejected_when_byo_disabled(): void
    {
        config(['tenant_infrastructure.enabled' => false]);

        $tenant = Tenant::create([
            'id' => 'byo-disabled',
            'name' => 'BYO Disabled',
            'slug' => 'byo-disabled',
        ]);

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
            ],
        ]);

        $tenant->delete();
    }

    public function test_external_database_config_is_encrypted_at_rest(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = $this->eligibleTenant('encrypted-tenant', 'encrypted-tenant');

        app(TenantInfrastructureService::class)->update($tenant, [
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'confirm_external_database' => true,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'super-secret',
            ],
        ]);

        $raw = DB::connection('central')->table('tenant_infrastructure')
            ->where('tenant_id', $tenant->id)
            ->value('database_config');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('super-secret', $raw);

        $snapshot = app(TenantInfrastructureService::class)->snapshot($tenant->fresh());
        $this->assertStringContainsString('•', $snapshot['database_config']['password']);

        $tenant->delete();
    }

    public function test_delete_tenant_job_skips_external_database(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = Tenant::create([
            'id' => 'external-delete',
            'name' => 'External Delete',
            'slug' => 'external-delete',
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
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        $tenant->setInternal('create_database', false);
        $tenant->save();

        (new \App\Domains\Tenancy\Jobs\DeleteTenantDatabaseJob($tenant))->handle(
            app(TenantInfrastructureService::class),
        );

        $this->assertTrue(app(TenantInfrastructureService::class)->usesExternalDatabase($tenant));

        $tenant->delete();
    }

    public function test_verify_endpoint_dispatches_infrastructure_job(): void
    {
        config(['tenant_infrastructure.enabled' => true]);
        Queue::fake();

        $tenant = $this->eligibleTenant('verify-job', 'verify-job');

        TenantInfrastructure::create([
            'tenant_id' => $tenant->id,
            'database_mode' => TenantInfrastructure::MODE_MANAGED,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'status' => TenantInfrastructure::STATUS_PENDING,
        ]);

        $this->actingAsPlatformAdmin();

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenant->id.'/infrastructure/verify')
            ->assertRedirect();

        Queue::assertPushed(VerifyTenantInfrastructureJob::class, fn (VerifyTenantInfrastructureJob $job) => $job->tenantId === $tenant->id);

        $tenant->delete();
    }

    public function test_read_only_database_password_is_required_when_username_set(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = $this->eligibleTenant('readonly-db', 'readonly-db');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TenantInfrastructureService::class)->update($tenant, [
            'database_mode' => TenantInfrastructure::MODE_EXTERNAL,
            'storage_mode' => TenantInfrastructure::MODE_MANAGED,
            'confirm_external_database' => true,
            'database_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'helpdesk',
                'username' => 'app',
                'password' => 'secret',
                'read_only_username' => 'readonly',
            ],
        ]);

        $tenant->delete();
    }

    private function adminLogin(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_platform_admin_can_view_infrastructure_page(): void
    {
        config(['tenant_infrastructure.enabled' => true]);

        $tenant = $this->eligibleTenant('admin-view', 'admin-view');

        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenant->id.'/infrastructure')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Tenants/Infrastructure')
                ->has('infrastructure')
                ->where('tenant.slug', 'admin-view'));

        $tenant->delete();
    }

    private function actingAsPlatformAdmin(): static
    {
        $this->adminLogin();

        return $this;
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
