<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Platform\Services\TenantPurgeService;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlatformTenantPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function adminLogin(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_new_tenant_database_uses_slug_name(): void
    {
        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Acme Inc',
            slug: 'acme',
            adminName: 'Acme Admin',
            adminEmail: 'admin@acme.test',
            adminPassword: 'password123',
        );

        $this->assertSame('tenant_acme', $tenant->database()->getName());
    }

    public function test_platform_admin_can_delete_tenant_and_database(): void
    {
        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Delete Me Co',
            slug: 'delete-me',
            adminName: 'Delete Admin',
            adminEmail: 'delete@test.com',
            adminPassword: 'password123',
        );

        $database = $tenant->database()->getName();
        $tenantId = $tenant->id;

        $this->adminLogin();

        $this->delete('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenantId, [
            'confirm_slug' => 'wrong-slug',
        ])->assertSessionHasErrors('confirm_slug');

        $this->delete('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenantId, [
            'confirm_slug' => 'delete-me',
        ])->assertRedirect();

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId], 'central');
        $this->assertFalse($this->databaseExists($database));
    }

    public function test_expired_trial_tenant_is_purged_after_grace_period(): void
    {
        \App\Domains\Tenancy\Models\CentralSetting::query()->update([
            'tenant_purge_grace_days' => 15,
            'tenant_purge_enabled' => true,
        ]);

        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Expired Trial Co',
            slug: 'expired-trial',
            adminName: 'Expired Admin',
            adminEmail: 'expired@test.com',
            adminPassword: 'password123',
        );

        $database = $tenant->database()->getName();
        $tenantId = $tenant->id;

        Subscription::query()
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->subDays(20),
            ]);

        $purged = app(TenantPurgeService::class)->purgeExpired();

        $this->assertCount(1, $purged);
        $this->assertDatabaseMissing('tenants', ['id' => $tenantId], 'central');
        $this->assertFalse($this->databaseExists($database));
    }

    public function test_purge_command_respects_disabled_setting(): void
    {
        \App\Domains\Tenancy\Models\CentralSetting::query()->update([
            'tenant_purge_enabled' => false,
        ]);

        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Protected Co',
            slug: 'protected',
            adminName: 'Protected Admin',
            adminEmail: 'protected@test.com',
            adminPassword: 'password123',
        );

        Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->update([
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->subDays(30),
            ]);

        Artisan::call('tenants:purge-expired');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id], 'central');
    }

    public function test_platform_admin_can_trigger_purge_from_settings(): void
    {
        \App\Domains\Tenancy\Models\CentralSetting::query()->update([
            'tenant_purge_grace_days' => 1,
            'tenant_purge_enabled' => true,
        ]);

        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Manual Purge Co',
            slug: 'manual-purge',
            adminName: 'Manual Admin',
            adminEmail: 'manual@test.com',
            adminPassword: 'password123',
        );

        $tenantId = $tenant->id;

        Subscription::query()
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->subDays(5),
            ]);

        $this->adminLogin();

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/settings/purge-expired-tenants')
            ->assertRedirect();

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId], 'central');
    }

    private function databaseExists(string $database): bool
    {
        $driver = config('database.connections.central.driver');

        if ($driver === 'sqlite') {
            return file_exists(database_path($database));
        }

        $result = \DB::connection('central')->select(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$database]
        );

        return $result !== [];
    }
}
