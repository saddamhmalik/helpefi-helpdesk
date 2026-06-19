<?php

namespace Tests\Feature;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparesServiceDeskTenant;
use Tests\TenantTestCase;

class ServiceDeskTest extends TenantTestCase
{
    use PreparesServiceDeskTenant;
    use RefreshDatabase;

    public function test_professional_plan_sees_service_desk_upgrade_page(): void
    {
        $this->prepareServiceDeskTenant('professional', []);

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantGet('/service-desk')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ServiceDesk/Upgrade'));
    }

    public function test_enterprise_plan_can_view_service_desk_hub(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->prepareServiceDeskTenant();

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantGet('/service-desk')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/Index')
                ->has('summaries', 4)
                ->has('totals'));
    }

    public function test_enterprise_plan_can_view_type_queue(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->prepareServiceDeskTenant();

        $statusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        Ticket::query()->create([
            'number' => 'HD-90001',
            'subject' => 'VPN outage',
            'description' => 'Users cannot connect.',
            'type' => ServiceCatalogItem::TYPE_INCIDENT,
            'ticket_status_id' => $statusId,
            'ticket_priority_id' => $priorityId,
        ]);

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantGet('/service-desk/queues/incident')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceDesk/Queue')
                ->where('type.value', 'incident')
                ->has('tickets.data', 1));
    }

    public function test_professional_plan_cannot_access_type_queue(): void
    {
        $this->prepareServiceDeskTenant('professional', []);

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantGet('/service-desk/queues/incident')
            ->assertForbidden();
    }

    public function test_invalid_queue_type_returns_not_found(): void
    {
        $this->prepareServiceDeskTenant();

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantGet('/service-desk/queues/invalid-type')
            ->assertNotFound();
    }

    public function test_billing_service_reports_service_desk_via_addon(): void
    {
        $this->prepareServiceDeskTenant('professional', []);
        $billing = app(\App\Domains\Billing\Services\BillingService::class);
        $this->assertFalse($billing->canUseFeature('service_desk'));

        $this->prepareServiceDeskTenant('professional', ['service_desk']);
        $billing = app(\App\Domains\Billing\Services\BillingService::class);
        $this->assertTrue($billing->canUseFeature('service_desk'));
    }
}
