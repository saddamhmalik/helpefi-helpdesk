<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Platform\Services\HelpefiLicenseService;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Services\TenantByoEligibilityService;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantByoEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
        config(['tenant_infrastructure.enabled' => true]);
        Queue::fake();
    }

    public function test_trial_workspace_is_not_eligible(): void
    {
        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(7),
        ], byoAllowed: true);

        $assessment = app(TenantByoEligibilityService::class)->assess($tenant);

        $this->assertFalse($assessment['eligible']);
        $this->assertTrue($assessment['on_trial']);
        $this->assertStringContainsString('Trial', implode(' ', $assessment['reasons']));

        $tenant->delete();
    }

    public function test_non_allowlisted_enterprise_workspace_is_not_eligible(): void
    {
        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertFalse(app(TenantByoEligibilityService::class)->isEligible($tenant));

        $tenant->delete();
    }

    public function test_allowlisted_enterprise_workspace_is_eligible(): void
    {
        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_ACTIVE,
        ], byoAllowed: true);

        $this->assertTrue(app(TenantByoEligibilityService::class)->isEligible($tenant));

        $tenant->delete();
    }

    public function test_storage_addon_only_does_not_enable_database_for_tenant_self_service(): void
    {
        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_ACTIVE,
            'active_addons' => ['byo_storage'],
        ], byoAllowed: true);

        $eligibility = app(TenantByoEligibilityService::class);

        $this->assertFalse($eligibility->canConfigureDatabase($tenant, false));
        $this->assertTrue($eligibility->canConfigureStorage($tenant, false));
        $this->assertTrue($eligibility->canConfigureDatabase($tenant, true));
        $this->assertTrue($eligibility->canConfigureStorage($tenant, true));

        $tenant->delete();
    }

    public function test_external_database_update_requires_eligibility(): void
    {
        config(['tenant_infrastructure.requires_enterprise_plan' => true]);
        Queue::fake();

        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'starter',
            'status' => Subscription::STATUS_ACTIVE,
        ], byoAllowed: true);

        $this->expectException(ValidationException::class);

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
            ],
        ]);

        $tenant->delete();
    }

    public function test_workspace_with_byo_database_addon_is_eligible_for_database(): void
    {
        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'professional',
            'status' => Subscription::STATUS_ACTIVE,
            'active_addons' => ['byo_database'],
        ]);

        $assessment = app(TenantByoEligibilityService::class)->assess($tenant);

        $this->assertTrue($assessment['database_eligible']);
        $this->assertFalse($assessment['storage_eligible']);

        $tenant->delete();
    }

    public function test_starter_with_database_addon_can_save_external_database_config(): void
    {
        config(['tenant_infrastructure.requires_enterprise_plan' => true]);
        Queue::fake();

        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'starter',
            'status' => Subscription::STATUS_ACTIVE,
            'active_addons' => ['byo_database'],
        ]);

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
            ],
        ]);

        $record = TenantInfrastructure::query()->where('tenant_id', $tenant->id)->first();

        $this->assertSame(TenantInfrastructure::MIGRATION_QUEUED, $record->database_migration_status);
        $this->assertSame('db.example.com', $record->database_config['host']);

        $tenant->delete();
    }

    public function test_platform_admin_can_toggle_byo_allowed(): void
    {
        $tenant = $this->createTenant(withSubscription: [
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenant->id, [
            'byo_allowed' => true,
        ])->assertRedirect();

        $this->assertTrue($tenant->fresh()->byo_allowed);

        $tenant->delete();
    }

    private function createTenant(array $withSubscription = [], bool $byoAllowed = false): Tenant
    {
        $tenant = Tenant::create([
            'id' => 'byo-'.uniqid(),
            'name' => 'BYO Tenant',
            'slug' => 'byo-'.uniqid(),
            'byo_allowed' => $byoAllowed,
        ]);

        if ($withSubscription !== []) {
            Subscription::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                array_merge([
                    'billing_interval' => 'month',
                ], $withSubscription),
            );
        }

        return $tenant->fresh(['subscription']);
    }
}
