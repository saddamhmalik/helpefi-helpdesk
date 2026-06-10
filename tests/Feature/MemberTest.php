<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Invitation;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_invite_member(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post('/settings/members/invite', [
                'email' => 'agent@example.com',
                'role' => 'agent',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'email' => 'agent@example.com',
            'role' => 'agent',
        ]);

        Queue::assertPushed(\App\Domains\Auth\Jobs\SendTeamInvitationJob::class);
    }

    public function test_admin_can_invite_member_with_team(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $department = \App\Domains\Workforce\Models\Department::query()->create([
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);
        $team = \App\Domains\Workforce\Models\Team::query()->create([
            'department_id' => $department->id,
            'name' => 'Tier 1',
            'slug' => 'tier-1',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/settings/members/invite', [
                'email' => 'teamagent@example.com',
                'role' => 'agent',
                'team_id' => $team->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'email' => 'teamagent@example.com',
            'role' => 'agent',
            'team_id' => $team->id,
        ]);

        Queue::assertPushed(\App\Domains\Auth\Jobs\SendTeamInvitationJob::class);
    }

    public function test_password_reset_routes_are_not_available(): void
    {
        $this->get('/reset-password/test-token')->assertNotFound();
        $this->post('/reset-password')->assertNotFound();
    }

    public function test_agent_cannot_access_members_page(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        $this->actingAs($agent)
            ->get('/settings/members')
            ->assertForbidden();
    }

    public function test_members_page_lists_employees_only(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->customer()->create([
            'name' => 'Jane Customer',
            'email' => 'customer@example.com',
        ]);

        $this->actingAs($admin)
            ->get('/settings/members')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Members')
                ->has('employees.data', 1)
                ->missing('customers')
                ->where('employees.data.0.email', $admin->email));
    }

    public function test_admin_can_view_customer_portal_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->customer()->create([
            'name' => 'Jane Customer',
            'email' => 'customer@example.com',
        ]);

        $this->actingAs($admin)
            ->get('/customers/accounts')
            ->assertRedirect(route('contacts.index', ['access' => 'portal']));
    }

    public function test_admin_can_remove_customer_portal_account(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)
            ->delete("/customers/accounts/{$customer->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
    }

    public function test_invitation_can_be_accepted(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $invitation = Invitation::query()->create([
            'email' => 'newagent@example.com',
            'token' => Invitation::generateToken(),
            'role' => 'agent',
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->post('/invitations/'.$invitation->token, [
            'name' => 'New Agent',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'newagent@example.com']);
        $this->assertAuthenticated();
    }
}
