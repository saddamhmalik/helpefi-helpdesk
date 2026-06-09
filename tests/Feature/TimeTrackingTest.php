<?php

namespace Tests\Feature;

use App\Domains\Reports\Models\SavedReport;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function apiToken(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');
    }

    private function createTicket(array $overrides = []): Ticket
    {
        return Ticket::query()->create(array_merge([
            'number' => 'HD-00100',
            'subject' => 'Billing question',
            'ticket_status_id' => TicketStatus::query()->where('slug', 'open')->value('id'),
            'ticket_priority_id' => TicketPriority::query()->where('slug', 'normal')->value('id'),
        ], $overrides));
    }

    public function test_agent_can_log_time_on_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $agent = User::factory()->create();
        $ticket = $this->createTicket();

        $this->actingAs($agent)
            ->post("/tickets/{$ticket->id}/time-entries", [
                'minutes' => 30,
                'note' => 'Investigated invoice mismatch',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_time_entries', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'minutes' => 30,
            'note' => 'Investigated invoice mismatch',
        ]);
    }

    public function test_agent_can_delete_own_time_entry(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $agent = User::factory()->create();
        $ticket = $this->createTicket();

        $entry = app(TimeTrackingService::class)->log($ticket->id, $agent->id, 20);

        $this->actingAs($agent)
            ->delete("/tickets/{$ticket->id}/time-entries/{$entry->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ticket_time_entries', ['id' => $entry->id]);
    }

    public function test_agent_cannot_delete_another_agents_time_entry(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = $this->createTicket();

        $entry = app(TimeTrackingService::class)->log($ticket->id, $owner->id, 15);

        $this->actingAs($other)
            ->delete("/tickets/{$ticket->id}/time-entries/{$entry->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('time');

        $this->assertDatabaseHas('ticket_time_entries', ['id' => $entry->id]);
    }

    public function test_admin_can_delete_any_time_entry(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $agent = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $ticket = $this->createTicket();

        $entry = app(TimeTrackingService::class)->log($ticket->id, $agent->id, 45);

        $this->actingAs($admin)
            ->delete("/tickets/{$ticket->id}/time-entries/{$entry->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ticket_time_entries', ['id' => $entry->id]);
    }

    public function test_report_rolls_up_time_by_agent_and_team(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $department = Department::query()->create([
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $team = Team::query()->create([
            'department_id' => $department->id,
            'name' => 'Tier 1',
            'slug' => 'tier-1',
            'is_active' => true,
        ]);

        $agentA = User::factory()->create(['name' => 'Agent A']);
        $agentB = User::factory()->create(['name' => 'Agent B']);

        $ticketA = $this->createTicket(['team_id' => $team->id, 'number' => 'HD-00101']);
        $ticketB = $this->createTicket(['team_id' => $team->id, 'number' => 'HD-00102']);

        app(TimeTrackingService::class)->log($ticketA->id, $agentA->id, 60);
        app(TimeTrackingService::class)->log($ticketB->id, $agentA->id, 30);
        app(TimeTrackingService::class)->log($ticketB->id, $agentB->id, 45);

        $report = app(ReportService::class)->run(SavedReport::TYPE_TIME_TRACKING, []);

        $this->assertSame('time_tracking', $report['format']);
        $this->assertSame(135, $report['summary']['total_minutes']);
        $this->assertSame(3, $report['summary']['entry_count']);

        $agentARow = collect($report['agents'])->firstWhere('agent_id', $agentA->id);
        $agentBRow = collect($report['agents'])->firstWhere('agent_id', $agentB->id);
        $teamRow = collect($report['teams'])->firstWhere('team_id', $team->id);

        $this->assertSame(90, $agentARow['total_minutes']);
        $this->assertSame(2, $agentARow['entry_count']);
        $this->assertSame(45, $agentBRow['total_minutes']);
        $this->assertSame(135, $teamRow['total_minutes']);
        $this->assertSame(3, $teamRow['entry_count']);
    }

    public function test_api_can_list_and_create_time_entries(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $agent = User::factory()->create();
        $ticket = $this->createTicket();
        $token = $this->apiToken($agent);

        $this->withToken($token)
            ->postJson("/api/v1/tickets/{$ticket->id}/time-entries", [
                'minutes' => 25,
                'note' => 'Phone call',
            ])
            ->assertCreated()
            ->assertJsonPath('minutes', 25);

        $this->withToken($token)
            ->getJson("/api/v1/tickets/{$ticket->id}/time-entries")
            ->assertOk()
            ->assertJsonPath('total_minutes', 25)
            ->assertJsonCount(1, 'entries');
    }
}
