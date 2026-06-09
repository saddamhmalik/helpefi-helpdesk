<?php

namespace Tests\Feature;

use App\Domains\Assignment\Models\AssignmentRule;
use App\Domains\Channels\Models\Channel;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function seedTicketMeta(): array
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        return [$status, $priority];
    }

    private function createRule(array $overrides = []): AssignmentRule
    {
        return AssignmentRule::query()->create(array_merge([
            'name' => 'Default routing',
            'strategy' => AssignmentRule::STRATEGY_ROUND_ROBIN,
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    public function test_admin_can_manage_assignment_rules(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/assignment')
            ->assertOk();

        $this->actingAs($admin)
            ->post('/settings/assignment', [
                'name' => 'Support queue',
                'strategy' => AssignmentRule::STRATEGY_ROUND_ROBIN,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assignment_rules', [
            'name' => 'Support queue',
            'strategy' => AssignmentRule::STRATEGY_ROUND_ROBIN,
        ]);
    }

    public function test_round_robin_assigns_unassigned_tickets_in_rotation(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);

        $agentA = User::factory()->create(['name' => 'Agent A']);
        $agentB = User::factory()->create(['name' => 'Agent B']);
        $agentC = User::factory()->create(['name' => 'Agent C']);
        $creator = User::factory()->create();

        $this->createRule();

        $payload = [
            'subject' => 'Needs help',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ];

        $this->actingAs($creator)->post('/tickets', $payload)->assertRedirect();
        $first = Ticket::query()->latest('id')->first();

        $this->actingAs($creator)->post('/tickets', array_merge($payload, ['subject' => 'Needs help 2']))->assertRedirect();
        $second = Ticket::query()->latest('id')->first();

        $this->actingAs($creator)->post('/tickets', array_merge($payload, ['subject' => 'Needs help 3']))->assertRedirect();
        $third = Ticket::query()->latest('id')->first();

        $assignees = collect([$first, $second, $third])->pluck('assigned_to')->unique()->values();

        $this->assertCount(3, $assignees);
        $this->assertContains($agentA->id, $assignees);
        $this->assertContains($agentB->id, $assignees);
        $this->assertContains($agentC->id, $assignees);
    }

    public function test_load_based_assigns_agent_with_fewest_open_tickets(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);

        $busy = User::factory()->create();
        $available = User::factory()->create();
        $creator = User::factory()->create();

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Existing one',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $busy->id,
        ]);

        Ticket::query()->create([
            'number' => 'HD-00002',
            'subject' => 'Existing two',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $busy->id,
        ]);

        $this->createRule(['strategy' => AssignmentRule::STRATEGY_LOAD_BASED]);

        $this->actingAs($creator)->post('/tickets', [
            'subject' => 'New ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'New ticket')->first();

        $this->assertSame($available->id, $ticket->assigned_to);
    }

    public function test_team_scoped_rule_only_assigns_team_members(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);

        $department = Department::query()->create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
        $team = Team::query()->create(['department_id' => $department->id, 'name' => 'Tier 1', 'slug' => 'tier-1', 'is_active' => true]);
        $teamAgent = User::factory()->create();
        $outsideAgent = User::factory()->create();
        $creator = User::factory()->create();

        $team->members()->attach($teamAgent->id, ['org_role' => Team::ROLE_MEMBER]);

        $this->createRule(['team_id' => $team->id]);

        $this->actingAs($creator)->post('/tickets', [
            'subject' => 'Team routed',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Team routed')->first();

        $this->assertSame($teamAgent->id, $ticket->assigned_to);
        $this->assertSame($team->id, $ticket->team_id);
        $this->assertNotSame($outsideAgent->id, $ticket->assigned_to);
    }

    public function test_manual_assignee_is_not_overwritten(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);

        $chosen = User::factory()->create();
        $other = User::factory()->create();
        $creator = User::factory()->create();

        $this->createRule();

        $this->actingAs($creator)->post('/tickets', [
            'subject' => 'Already assigned',
            'assigned_to' => $chosen->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Already assigned')->first();

        $this->assertSame($chosen->id, $ticket->assigned_to);
        $this->assertNotSame($other->id, $ticket->assigned_to);
    }

    public function test_unassigning_ticket_runs_assignment_again(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);

        $agentA = User::factory()->create();
        $agentB = User::factory()->create();
        $creator = User::factory()->create();

        $this->createRule(['last_assigned_user_id' => $agentA->id]);

        $ticket = Ticket::query()->create([
            'number' => 'HD-00010',
            'subject' => 'Will unassign',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $agentA->id,
        ]);

        $this->actingAs($creator)
            ->patch("/tickets/{$ticket->id}", ['assigned_to' => ''])
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame($agentB->id, $ticket->assigned_to);
    }

    public function test_channel_filter_limits_matching_rules(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class]);

        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');
        $webChannelId = Channel::query()->where('slug', 'web')->value('id');
        $department = Department::query()->create(['name' => 'Routing', 'slug' => 'routing', 'is_active' => true]);
        $emailTeam = Team::query()->create(['department_id' => $department->id, 'name' => 'Email team', 'slug' => 'email-team', 'is_active' => true]);
        $webTeam = Team::query()->create(['department_id' => $department->id, 'name' => 'Web team', 'slug' => 'web-team', 'is_active' => true]);
        $emailAgent = User::factory()->create();
        $webAgent = User::factory()->create();
        $creator = User::factory()->create();

        $emailTeam->members()->attach($emailAgent->id, ['org_role' => Team::ROLE_MEMBER]);
        $webTeam->members()->attach($webAgent->id, ['org_role' => Team::ROLE_MEMBER]);

        AssignmentRule::query()->create([
            'name' => 'Email only',
            'strategy' => AssignmentRule::STRATEGY_ROUND_ROBIN,
            'is_active' => true,
            'sort_order' => 0,
            'team_id' => $emailTeam->id,
            'channel_ids' => [$emailChannelId],
        ]);

        AssignmentRule::query()->create([
            'name' => 'Web only',
            'strategy' => AssignmentRule::STRATEGY_ROUND_ROBIN,
            'is_active' => true,
            'sort_order' => 1,
            'team_id' => $webTeam->id,
            'channel_ids' => [$webChannelId],
        ]);

        $this->actingAs($creator)->post('/tickets', [
            'subject' => 'Web ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Web ticket')->first();

        $this->assertSame($webChannelId, $ticket->channel_id);
        $this->assertSame($webAgent->id, $ticket->assigned_to);
        $this->assertSame($webTeam->id, $ticket->team_id);
    }
}
