<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
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

    public function test_platform_admin_can_view_dashboard_and_tenants(): void
    {
        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Dashboard'));

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/tenants')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Tenants/Index'));
    }

    public function test_platform_admin_can_block_tenant_and_change_plan(): void
    {
        $tenant = app(\App\Domains\Tenancy\Services\TenantProvisioningService::class)->provision(
            organizationName: 'Block Test Co',
            slug: 'block-test',
            adminName: 'Block Admin',
            adminEmail: 'block@test.com',
            adminPassword: 'password123',
        );

        $this->adminLogin();

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenant->id, [
            'is_blocked' => true,
        ])->assertRedirect();

        $this->assertTrue($tenant->fresh()->is_blocked);

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenant->id, [
            'plan' => 'professional',
        ])->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan' => 'professional',
            'status' => Subscription::STATUS_ACTIVE,
        ], 'central');
    }

    public function test_platform_admin_can_assign_plan_with_addons_and_inr_billing(): void
    {
        \App\Domains\Tenancy\Models\CentralSetting::query()->update([
            'currency' => 'USD',
            'india_pricing' => true,
        ]);

        $tenant = app(\App\Domains\Tenancy\Services\TenantProvisioningService::class)->provision(
            organizationName: 'Addon Test Co',
            slug: 'addon-test',
            adminName: 'Addon Admin',
            adminEmail: 'addon@test.com',
            adminPassword: 'password123',
        );

        $this->adminLogin();

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/tenants/'.$tenant->id, [
            'plan' => 'enterprise',
            'billing_interval' => 'month',
            'billing_currency' => 'INR',
            'addons' => ['service_desk'],
            'custom_price' => 14999,
        ])->assertRedirect();

        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->first();

        $this->assertSame('enterprise', $subscription->plan);
        $this->assertSame('INR', $subscription->currency);
        $this->assertSame(14999, $subscription->custom_amount);
        $this->assertSame(['service_desk'], $subscription->active_addons);
    }

    public function test_blocked_tenant_redirects_to_blocked_page(): void
    {
        $provisioning = app(\App\Domains\Tenancy\Services\TenantProvisioningService::class);
        $tenant = $provisioning->provision(
            organizationName: 'Blocked Co',
            slug: 'blocked-co',
            adminName: 'Blocked Admin',
            adminEmail: 'blocked@test.com',
            adminPassword: 'password123',
        );

        $tenant->update(['is_blocked' => true]);
        $domain = $tenant->domains()->value('domain');

        $this->get('http://'.$domain.'/login')
            ->assertRedirect(route('tenant.blocked'));
    }
}
