<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceCatalog\Models\ServiceCategory;
use App\Domains\ServiceDesk\Models\ApprovalRequest;
use App\Domains\ServiceDesk\Models\ServiceDeskSetting;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class ServiceDeskApprovalTest extends TenantTestCase
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

    public function test_catalog_submission_with_approval_creates_pending_request(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('enterprise');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $approver = User::factory()->create();
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');
        $pendingStatusId = TicketStatus::query()->where('slug', 'pending')->value('id');

        $category = ServiceCategory::query()->create([
            'name' => 'Access',
            'slug' => 'access',
            'is_active' => true,
        ]);

        $item = ServiceCatalogItem::query()->create([
            'service_category_id' => $category->id,
            'name' => 'VPN access',
            'slug' => 'vpn-access',
            'ticket_type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
            'ticket_priority_id' => $priorityId,
            'requires_approval' => true,
            'approver_user_ids' => [$approver->id],
            'is_public' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->tenantPost('/portal/services/vpn-access', [
                'name' => 'Alex Agent',
                'email' => 'alex@example.com',
                'details' => 'Need VPN for travel.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'VPN access',
            'service_catalog_item_id' => $item->id,
            'ticket_status_id' => $pendingStatusId,
        ]);

        $this->assertDatabaseHas('approval_requests', [
            'service_catalog_item_id' => $item->id,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
    }

    public function test_approver_can_approve_request_and_open_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('enterprise');

        $approver = User::factory()->create();
        $openStatusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $pendingStatusId = TicketStatus::query()->where('slug', 'pending')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-91001',
            'subject' => 'Laptop upgrade',
            'ticket_status_id' => $pendingStatusId,
            'ticket_priority_id' => $priorityId,
            'type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
        ]);

        $approval = ApprovalRequest::query()->create([
            'ticket_id' => $ticket->id,
            'subject' => 'Laptop upgrade',
            'status' => ApprovalRequest::STATUS_PENDING,
            'current_step' => 1,
        ]);

        \App\Domains\ServiceDesk\Models\ApprovalRequestStep::query()->create([
            'approval_request_id' => $approval->id,
            'step_order' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);

        $this->actingAs($approver)
            ->tenantPost("/service-desk/approvals/{$approval->id}/approve", ['note' => 'Approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_requests', [
            'id' => $approval->id,
            'status' => ApprovalRequest::STATUS_APPROVED,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'ticket_status_id' => $openStatusId,
        ]);
    }

    public function test_change_ticket_triggers_global_change_approval(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('enterprise');

        $approver = User::factory()->create();
        ServiceDeskSetting::query()->create([
            'change_requires_approval' => true,
            'change_approver_user_ids' => [$approver->id],
        ]);

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $openStatusId = TicketStatus::query()->where('slug', 'open')->value('id');
        $priorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $this->actingAs($admin)
            ->tenantPost('/tickets', [
                'subject' => 'Deploy patch',
                'description' => 'Weekend maintenance',
                'ticket_status_id' => $openStatusId,
                'ticket_priority_id' => $priorityId,
                'type' => ServiceCatalogItem::TYPE_CHANGE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_requests', [
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
    }
}
