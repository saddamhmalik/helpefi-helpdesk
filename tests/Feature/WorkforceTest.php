<?php

namespace Tests\Feature;

use App\Domains\Sla\Models\SlaEscalationRule;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_departments_and_teams(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create();

        $this->actingAs($admin)
            ->get('/settings/workforce')
            ->assertOk();

        $this->actingAs($admin)
            ->post('/settings/workforce/departments', [
                'name' => 'Engineering',
                'head_user_id' => $admin->id,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $department = Department::query()->where('name', 'Engineering')->first();
        $this->assertNotNull($department);
        $this->assertSame($admin->id, $department->head_user_id);

        $this->actingAs($admin)
            ->post('/settings/workforce/teams', [
                'department_id' => $department->id,
                'name' => 'Platform',
                'lead_user_id' => $agent->id,
                'is_active' => true,
                'sort_order' => 0,
                'members' => [
                    ['user_id' => $agent->id, 'org_role' => 'team_lead'],
                ],
            ])
            ->assertRedirect();

        $team = Team::query()->where('name', 'Platform')->first();
        $this->assertNotNull($team);
        $this->assertTrue($team->members()->whereKey($agent->id)->exists());
    }

    public function test_ticket_can_be_created_with_department_and_team(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $admin = User::factory()->admin()->create();
        $department = Department::query()->create([
            'name' => 'Support',
            'slug' => 'support',
            'head_user_id' => $admin->id,
            'is_active' => true,
        ]);
        $team = Team::query()->create([
            'department_id' => $department->id,
            'name' => 'Tier 1',
            'slug' => 'tier-1',
            'lead_user_id' => $admin->id,
            'is_active' => true,
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($admin)
            ->post('/tickets', [
                'subject' => 'Org routing test',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
                'department_id' => $department->id,
                'team_id' => $team->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Org routing test',
            'department_id' => $department->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_sla_escalation_triggers_level_one_actions(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@helpdesk.test']);
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class, WorkforceSeeder::class]);
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $department = Department::query()->where('slug', 'support')->first();
        $team = Team::query()->where('slug', 'tier-1')->first();

        $this->actingAs($admin)->post('/tickets', [
            'subject' => 'Escalation test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $admin->id,
            'department_id' => $department->id,
            'team_id' => $team->id,
        ]);

        $ticket = Ticket::query()->where('subject', 'Escalation test')->first();
        $ticket->slaTimer()->update([
            'first_response_due_at' => now()->subHour(),
            'first_response_breached' => true,
        ]);

        $this->artisan('sla:check-breaches')->assertSuccessful();

        $this->assertDatabaseHas('sla_escalation_logs', [
            'ticket_id' => $ticket->id,
            'level' => 1,
            'breach_type' => SlaEscalationRule::BREACH_FIRST_RESPONSE,
        ]);
    }

    public function test_sla_breach_lowers_assignee_performance_score(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $agent = User::factory()->create(['performance_score' => 100]);
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs($agent)->post('/tickets', [
            'subject' => 'Performance breach',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $agent->id,
        ]);

        $ticket = Ticket::query()->where('subject', 'Performance breach')->first();
        $ticket->slaTimer()->update([
            'first_response_due_at' => now()->subHour(),
        ]);

        $this->artisan('sla:check-breaches')->assertSuccessful();

        $agent->refresh();
        $this->assertSame(95.0, (float) $agent->performance_score);
        $this->assertDatabaseHas('agent_performance_events', [
            'user_id' => $agent->id,
            'ticket_id' => $ticket->id,
            'event_type' => 'sla_first_response_breach',
            'points' => -5,
        ]);
    }

    public function test_admin_can_view_agent_performance(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create(['performance_score' => 88.5]);

        $this->actingAs($admin)
            ->get("/settings/performance/{$agent->id}")
            ->assertOk();
    }
}
