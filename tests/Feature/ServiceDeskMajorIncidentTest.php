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

class ServiceDeskMajorIncidentTest extends TenantTestCase
{
    use RefreshDatabase;

    private function setPlan(string $plan): void
    {
        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => $plan,
                'status' => Subscription::STATUS_ACTIVE,
                'renews_at' => now()->addMonth(),
            ],
        );
    }

    public function test_incident_can_be_declared_major_and_open_war_room(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('enterprise');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'urgent')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-93001',
            'subject' => 'Company-wide email outage',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_INCIDENT,
        ]);

        $this->actingAs($admin)
            ->tenantPost("/tickets/{$ticket->id}/major-incident")
            ->assertRedirect("/service-desk/major-incidents/{$ticket->id}/war-room");

        $this->assertDatabaseHas('major_incident_records', [
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
            'declared_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->tenantGet("/service-desk/major-incidents/{$ticket->id}/war-room")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/MajorIncidents/WarRoom')
                ->where('majorIncident.status', 'active'));
    }

    public function test_major_incident_can_be_resolved_and_review_completed(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('enterprise');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'urgent')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-93002',
            'subject' => 'VPN cluster failure',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_INCIDENT,
        ]);

        MajorIncidentRecord::query()->create([
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
            'declared_by_user_id' => $admin->id,
            'declared_at' => now(),
            'coordinator_user_ids' => [$admin->id],
        ]);

        $this->actingAs($admin)
            ->tenantPost("/tickets/{$ticket->id}/major-incident/resolve")
            ->assertRedirect();

        $this->assertDatabaseHas('major_incident_records', [
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_RESOLVED,
        ]);

        $this->actingAs($admin)
            ->tenantPost("/tickets/{$ticket->id}/major-incident/complete-review", [
                'summary' => 'VPN cluster restored after failover.',
                'lessons_learned' => 'Improve health checks.',
            ])
            ->assertRedirect("/tickets/{$ticket->id}");

        $this->assertDatabaseHas('major_incident_records', [
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_CLOSED,
        ]);
    }

    public function test_major_incidents_index_lists_active_incidents(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('enterprise');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'urgent')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-93003',
            'subject' => 'Payment gateway down',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_INCIDENT,
        ]);

        MajorIncidentRecord::query()->create([
            'ticket_id' => $ticket->id,
            'status' => MajorIncidentRecord::STATUS_ACTIVE,
            'declared_by_user_id' => $admin->id,
            'declared_at' => now(),
        ]);

        $this->actingAs($admin)
            ->tenantGet('/service-desk/major-incidents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/MajorIncidents/Index')
                ->where('active_count', 1)
                ->has('entries', 1));
    }

    public function test_non_incident_cannot_be_declared_major(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('enterprise');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-93004',
            'subject' => 'New laptop',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
        ]);

        $this->actingAs($admin)
            ->tenantPost("/tickets/{$ticket->id}/major-incident")
            ->assertSessionHasErrors('ticket');
    }
}
