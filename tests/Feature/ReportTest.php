<?php

namespace Tests\Feature;

use App\Domains\Reports\Models\SavedReport;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedTicketMeta(): array
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $closed = TicketStatus::query()->create(['name' => 'Closed', 'slug' => 'closed', 'color' => '#000', 'sort_order' => 2, 'is_closed' => true]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        return [$status, $closed, $priority];
    }

    public function test_agent_can_access_reports_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk();
    }

    public function test_agent_can_run_tickets_report(): void
    {
        [$status, , $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Report ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'assigned_to' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/reports?type=tickets&run=1')
            ->assertOk();
    }

    public function test_agent_can_save_report(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/reports', [
                'name' => 'Weekly tickets',
                'type' => SavedReport::TYPE_TICKETS,
                'filters' => ['date_from' => now()->subWeek()->toDateString()],
                'is_default' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('saved_reports', [
            'user_id' => $user->id,
            'name' => 'Weekly tickets',
            'type' => SavedReport::TYPE_TICKETS,
            'is_default' => true,
        ]);
    }

    public function test_agent_can_export_tickets_csv(): void
    {
        [$status, , $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Export me',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)
            ->get('/reports/export?type=tickets');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Export me', $response->streamedContent());
    }

    public function test_dashboard_includes_enhanced_widgets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->has('stats.createdThisWeek')
                ->has('stats.resolvedThisWeek')
                ->has('stats.slaBreaches')
                ->has('volumeTrend')
                ->has('topAgents')
            );
    }

    public function test_sla_breaches_report_lists_breached_tickets(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $user = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $policy = SlaPolicy::query()->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Breached ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketSlaTimer::query()->create([
            'ticket_id' => $ticket->id,
            'sla_policy_id' => $policy->id,
            'first_response_breached' => true,
            'resolution_breached' => false,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/reports/run?type=sla_breaches')
            ->assertOk()
            ->assertJsonPath('format', 'tickets')
            ->assertJsonCount(1, 'rows.data');
    }
}
