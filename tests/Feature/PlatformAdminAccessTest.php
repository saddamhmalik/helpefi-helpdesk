<?php

namespace Tests\Feature;

use App\Models\PlatformUser;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdminAccessTest extends TestCase
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

    public function test_super_admin_can_manage_profile_password_and_users(): void
    {
        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Dashboard'));

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/login')
            ->assertRedirect('http://'.config('tenancy.central_app_domain').'/admin/dashboard');

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Profile'));

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/profile/password', [
            'current_password' => PlatformUserSeeder::DEFAULT_PASSWORD,
            'password' => 'NewPlatformPass123!',
            'password_confirmation' => 'NewPlatformPass123!',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('NewPlatformPass123!', PlatformUser::query()->first()->password));

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Users/Index'));

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/roles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Roles/Index'));
    }

    public function test_support_user_cannot_manage_platform_users(): void
    {
        $support = PlatformUser::query()->create([
            'name' => 'Support User',
            'email' => 'support@helpdesk.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $support->syncRoles(['support']);

        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => 'support@helpdesk.test',
            'password' => 'password123',
        ]);

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/tenants')->assertOk();
        $this->get('http://'.config('tenancy.central_app_domain').'/admin/users')->assertForbidden();
        $this->get('http://'.config('tenancy.central_app_domain').'/admin/roles')->assertForbidden();
    }
}
