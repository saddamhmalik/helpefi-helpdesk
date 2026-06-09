<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_login_redirects_to_settings(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.hub'));
    }

    public function test_agent_login_redirects_to_dashboard(): void
    {
        $agent = User::factory()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $agent->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_view_settings_overview(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Overview'));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect('/settings');
    }

    public function test_agent_is_redirected_from_settings_overview(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/settings')
            ->assertRedirect(route('settings.profile'));
    }

    public function test_dashboard_remains_available_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
    }
}
