<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSubscriptionTest extends TestCase
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

    public function test_platform_admin_can_view_subscriptions_page(): void
    {
        app(TenantProvisioningService::class)->provision(
            organizationName: 'Sub List Co',
            slug: 'sub-list',
            adminName: 'Sub Admin',
            adminEmail: 'sub@list.test',
            adminPassword: 'password123',
        );

        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Subscriptions/Index')
                ->has('subscriptions.data', 1)
                ->where('subscriptions.data.0.tenant.slug', 'sub-list'));
    }

    public function test_grace_filter_shows_cancelling_subscriptions(): void
    {
        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Grace Co',
            slug: 'grace-co',
            adminName: 'Grace Admin',
            adminEmail: 'grace@test.com',
            adminPassword: 'password123',
        );

        Subscription::query()->where('tenant_id', $tenant->id)->update([
            'plan' => 'professional',
            'status' => Subscription::STATUS_ACTIVE,
            'cancelled_at' => now(),
            'access_ends_at' => now()->addDays(3),
        ]);

        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/subscriptions?status=grace')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('subscriptions.data', 1)
                ->where('subscriptions.data.0.cancellation_pending', true));
    }
}
