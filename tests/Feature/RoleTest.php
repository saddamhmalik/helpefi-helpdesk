<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_view_roles_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/roles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Roles'));
    }

    public function test_agent_cannot_view_roles_page(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/settings/roles')
            ->assertForbidden();
    }

    public function test_admin_can_create_custom_role_with_permissions(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/settings/roles', [
                'name' => 'Support Lead',
                'permissions' => ['access.agent', 'tickets.view', 'tickets.manage'],
            ])
            ->assertRedirect();

        $role = Role::query()->where('name', 'support_lead')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('tickets.manage'));
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::query()->create(['name' => 'viewer', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->put("/settings/roles/{$role->id}", [
                'permissions' => ['access.agent', 'tickets.view'],
            ])
            ->assertRedirect();

        $this->assertTrue($role->fresh()->hasPermissionTo('tickets.view'));
        $this->assertFalse($role->fresh()->hasPermissionTo('tickets.manage'));
    }

    public function test_protected_role_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::query()->where('name', 'admin')->first();

        $this->actingAs($admin)
            ->delete("/settings/roles/{$role->id}")
            ->assertSessionHasErrors('role');
    }

    public function test_custom_role_user_can_access_agent_portal(): void
    {
        $role = Role::query()->create(['name' => 'helpdesk_viewer', 'guard_name' => 'web']);
        $role->givePermissionTo('access.agent');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_members_page_lists_custom_roles(): void
    {
        $admin = User::factory()->admin()->create();

        Role::query()->create(['name' => 'team_lead', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->get('/settings/members')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Members')
                ->where('roles', fn ($roles) => collect($roles)->contains('team_lead')));
    }
}
