<?php

namespace Tests\Feature;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Models\TicketView;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketViewSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_user_can_save_private_view_with_advanced_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/ticket-views', [
                'name' => 'Unassigned queue',
                'visibility' => 'private',
                'filters' => [
                    'unassigned' => true,
                    'priority_id' => TicketPriority::query()->create(['name' => 'High', 'slug' => 'high', 'sort_order' => 1])->id,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_views', [
            'user_id' => $user->id,
            'name' => 'Unassigned queue',
            'visibility' => 'private',
        ]);
    }

    public function test_team_member_can_access_shared_team_view(): void
    {
        $owner = User::factory()->create();
        $teammate = User::factory()->create();
        $department = Department::query()->create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
        $team = Team::query()->create([
            'department_id' => $department->id,
            'name' => 'Tier 1',
            'slug' => 'tier-1',
            'is_active' => true,
        ]);

        $team->members()->attach([$owner->id, $teammate->id]);

        TicketView::query()->create([
            'user_id' => $owner->id,
            'name' => 'Team backlog',
            'visibility' => 'team',
            'team_id' => $team->id,
            'filters' => ['unassigned' => true],
        ]);

        $this->actingAs($teammate)
            ->get('/tickets?view_id='.TicketView::query()->value('id'))
            ->assertOk();
    }

    public function test_non_member_cannot_access_shared_team_view(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $department = Department::query()->create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
        $team = Team::query()->create([
            'department_id' => $department->id,
            'name' => 'Tier 1',
            'slug' => 'tier-1',
            'is_active' => true,
        ]);

        $team->members()->attach($owner->id);

        $view = TicketView::query()->create([
            'user_id' => $owner->id,
            'name' => 'Team backlog',
            'visibility' => 'team',
            'team_id' => $team->id,
            'filters' => ['search' => 'billing'],
        ]);

        $this->actingAs($outsider)
            ->get('/tickets?view_id='.$view->id)
            ->assertNotFound();
    }

    public function test_advanced_filters_narrow_ticket_results(): void
    {
        $user = User::factory()->create();
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Assigned ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $user->id,
        ]);

        Ticket::query()->create([
            'number' => 'HD-00002',
            'subject' => 'Unassigned ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($user)
            ->get('/tickets?unassigned=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Index')
                ->where('tickets.total', 1)
                ->where('tickets.data.0.number', 'HD-00002'));
    }
}
