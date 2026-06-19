<?php

namespace Tests\Feature;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Models\ChangeRecord;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparesServiceDeskTenant;
use Tests\TenantTestCase;

class ServiceDeskChangeProblemTest extends TenantTestCase
{
    use PreparesServiceDeskTenant;
    use RefreshDatabase;

    public function test_change_record_can_be_updated_on_change_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->prepareServiceDeskTenant();

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-92001',
            'subject' => 'Database migration',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_CHANGE,
        ]);

        ChangeRecord::query()->create(['ticket_id' => $ticket->id]);

        $this->actingAs($admin)
            ->tenantPut("/tickets/{$ticket->id}/change-record", [
                'risk' => 'high',
                'impact' => 'Production downtime possible',
                'planned_start' => now()->addDay()->toIso8601String(),
                'planned_end' => now()->addDays(2)->toIso8601String(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('change_records', [
            'ticket_id' => $ticket->id,
            'risk' => 'high',
        ]);
    }

    public function test_change_calendar_lists_scheduled_changes(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->prepareServiceDeskTenant();

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-92002',
            'subject' => 'Firewall rule update',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_CHANGE,
        ]);

        ChangeRecord::query()->create([
            'ticket_id' => $ticket->id,
            'planned_start' => now()->startOfMonth()->addDays(3),
            'planned_end' => now()->startOfMonth()->addDays(3)->addHours(2),
        ]);

        $this->actingAs($admin)
            ->tenantGet('/service-desk/changes/calendar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/ChangeCalendar')
                ->has('entries', 1));
    }

    public function test_problem_ticket_can_link_and_unlink_incidents(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->prepareServiceDeskTenant();

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $problem = Ticket::query()->create([
            'number' => 'HD-92003',
            'subject' => 'Email delivery failures',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_PROBLEM,
        ]);

        $incident = Ticket::query()->create([
            'number' => 'HD-92004',
            'subject' => 'Cannot send mail',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_INCIDENT,
        ]);

        \App\Domains\ServiceDesk\Models\ProblemRecord::query()->create(['ticket_id' => $problem->id]);

        $this->actingAs($admin)
            ->tenantPost("/tickets/{$problem->id}/problem-incidents", [
                'incident_ticket_id' => $incident->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('problem_incident_links', [
            'problem_ticket_id' => $problem->id,
            'incident_ticket_id' => $incident->id,
        ]);

        $this->actingAs($admin)
            ->tenantDelete("/tickets/{$problem->id}/problem-incidents/{$incident->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('problem_incident_links', [
            'problem_ticket_id' => $problem->id,
            'incident_ticket_id' => $incident->id,
        ]);
    }

    public function test_professional_plan_cannot_update_change_record(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->prepareServiceDeskTenant('professional', []);

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-92005',
            'subject' => 'Patch Tuesday',
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_CHANGE,
        ]);

        $this->actingAs($admin)
            ->tenantPut("/tickets/{$ticket->id}/change-record", ['risk' => 'low'])
            ->assertForbidden();
    }
}
