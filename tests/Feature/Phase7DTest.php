<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\MajorIncidentRecord;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class Phase7DTest extends TenantTestCase
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

    public function test_agent_can_declare_major_incident(): void
    {
        $ticket = $this->makeTicket(ServiceCatalogItem::TYPE_INCIDENT, ['subject' => 'Platform outage']);

        $this->actingAs($this->admin())
            ->tenantPost("/tickets/{$ticket->id}/major-incident")
            ->assertRedirect("/service-desk/major-incidents/{$ticket->id}/war-room");

        $this->assertDatabaseHas('major_incident_records', [
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
        ]);
    }

    public function test_major_incidents_index_lists_active_incidents(): void
    {
        $ticket = $this->makeTicket(ServiceCatalogItem::TYPE_INCIDENT, ['subject' => 'Email down']);

        MajorIncidentRecord::query()->create([
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
            'declared_by_user_id' => $this->admin()->id,
            'declared_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->tenantGet('/service-desk/major-incidents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/MajorIncidents/Index')
                ->has('entries', 1)
                ->where('active_count', 1));
    }

    public function test_war_room_is_accessible_for_declared_incident(): void
    {
        $ticket = $this->makeTicket(ServiceCatalogItem::TYPE_INCIDENT);

        MajorIncidentRecord::query()->create([
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
            'declared_by_user_id' => $this->admin()->id,
            'declared_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->tenantGet("/service-desk/major-incidents/{$ticket->id}/war-room")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/MajorIncidents/WarRoom')
                ->has('majorIncident')
                ->where('majorIncident.status', MajorIncidentRecord::STATUS_ACTIVE));
    }

    public function test_resolve_and_complete_review_flow(): void
    {
        $ticket = $this->makeTicket(ServiceCatalogItem::TYPE_INCIDENT);

        MajorIncidentRecord::query()->create([
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
            'declared_by_user_id' => $this->admin()->id,
            'declared_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->tenantPost("/tickets/{$ticket->id}/major-incident/resolve")
            ->assertRedirect();

        $this->assertDatabaseHas('major_incident_records', [
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_RESOLVED,
        ]);

        $this->actingAs($this->admin())
            ->tenantPost("/tickets/{$ticket->id}/major-incident/complete-review", [
                'summary' => 'Database failover restored service.',
                'lessons_learned' => 'Improve monitoring alerts.',
            ])
            ->assertRedirect("/tickets/{$ticket->id}");

        $this->assertDatabaseHas('major_incident_records', [
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_CLOSED,
            'summary' => 'Database failover restored service.',
        ]);
    }
}
