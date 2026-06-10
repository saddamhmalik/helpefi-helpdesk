<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ChangeRecord;
use App\Domains\ServiceDesk\Models\ProblemRecord;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class Phase7CTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TicketLookupSeeder::class);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => 'enterprise',
                'status' => Subscription::STATUS_ACTIVE,
                'renews_at' => now()->addMonth(),
            ],
        );
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    private function makeTicket(string $type, array $overrides = []): Ticket
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();

        return Ticket::query()->create(array_merge([
            'subject' => ucfirst($type).' ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'type' => $type,
        ], $overrides));
    }

    public function test_change_record_is_created_for_change_tickets(): void
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();

        $this->actingAs($this->admin())
            ->tenantPost('/tickets', [
                'subject' => 'Firewall rule update',
                'description' => 'Add new ingress rule',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
                'type' => ServiceCatalogItem::TYPE_CHANGE,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('change_records', 1);
    }

    public function test_agent_can_update_change_record(): void
    {
        $ticket = $this->makeTicket(ServiceCatalogItem::TYPE_CHANGE);
        ChangeRecord::query()->firstOrCreate(['ticket_id' => $ticket->id]);

        $this->actingAs($this->admin())
            ->tenantPut("/tickets/{$ticket->id}/change-record", [
                'risk' => ChangeRecord::RISK_HIGH,
                'impact' => 'Customer-facing outage window',
                'planned_start' => now()->addDay()->toDateTimeString(),
                'planned_end' => now()->addDays(2)->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('change_records', [
            'ticket_id' => $ticket->id,
            'risk' => ChangeRecord::RISK_HIGH,
            'impact' => 'Customer-facing outage window',
        ]);
    }

    public function test_change_calendar_lists_scheduled_changes(): void
    {
        $ticket = $this->makeTicket(ServiceCatalogItem::TYPE_CHANGE, ['subject' => 'Database upgrade']);

        ChangeRecord::query()->updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'risk' => ChangeRecord::RISK_LOW,
                'planned_start' => now()->addDays(3),
                'planned_end' => now()->addDays(3)->addHours(2),
            ],
        );

        $this->actingAs($this->admin())
            ->tenantGet('/service-desk/changes/calendar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/ChangeCalendar')
                ->has('entries', 1));
    }

    public function test_problem_ticket_links_and_unlinks_incidents(): void
    {
        $problem = $this->makeTicket(ServiceCatalogItem::TYPE_PROBLEM);
        $incident = $this->makeTicket(ServiceCatalogItem::TYPE_INCIDENT, ['subject' => 'VPN drops']);

        ProblemRecord::query()->firstOrCreate(['ticket_id' => $problem->id]);

        $this->actingAs($this->admin())
            ->tenantPost("/tickets/{$problem->id}/problem-incidents", [
                'incident_ticket_id' => $incident->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('problem_incident_links', [
            'problem_ticket_id' => $problem->id,
            'incident_ticket_id' => $incident->id,
        ]);

        $this->actingAs($this->admin())
            ->tenantDelete("/tickets/{$problem->id}/problem-incidents/{$incident->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('problem_incident_links', [
            'problem_ticket_id' => $problem->id,
            'incident_ticket_id' => $incident->id,
        ]);
    }

    public function test_ticket_show_includes_change_and_problem_snapshots(): void
    {
        $change = $this->makeTicket(ServiceCatalogItem::TYPE_CHANGE);
        ChangeRecord::query()->firstOrCreate(['ticket_id' => $change->id]);

        $this->actingAs($this->admin())
            ->tenantGet("/tickets/{$change->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Show')
                ->has('changeRecord')
                ->where('changeRecord.ticket_id', $change->id));
    }
}
