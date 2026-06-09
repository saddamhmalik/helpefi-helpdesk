<?php

namespace Tests\Feature;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_view_member_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create(['name' => 'Profile Agent']);
        $agent->assignRole('agent');

        $department = Department::query()->create([
            'name' => 'Finance',
            'slug' => 'finance',
            'is_active' => true,
        ]);

        $team = Team::query()->create([
            'department_id' => $department->id,
            'name' => 'Fin Team',
            'slug' => 'fin-team',
            'is_active' => true,
        ]);

        $agent->teams()->attach($team->id, ['org_role' => 'member']);

        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Assigned ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $agent->id,
            'department_id' => $department->id,
            'team_id' => $team->id,
        ]);

        $this->actingAs($admin)
            ->get("/settings/members/{$agent->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Members/Show')
                ->where('member.name', 'Profile Agent')
                ->where('ticketStats.assigned.open', 1)
                ->where('ticketStats.department.total', 1)
                ->has('departments', 1)
                ->has('recentAssignedTickets', 1));
    }

    public function test_agent_cannot_view_member_profile(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        $target = User::factory()->create();
        $target->assignRole('agent');

        $this->actingAs($agent)
            ->get("/settings/members/{$target->id}")
            ->assertForbidden();
    }
}
