<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceCatalog\Models\ServiceCategory;
use App\Domains\ServiceDesk\Models\ApprovalRequest;
use App\Domains\ServiceDesk\Models\ServiceDeskSetting;
use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class Phase7BTest extends TenantTestCase
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

    private function agent(): User
    {
        return User::query()->where('email', 'agent@helpdesk.test')->firstOrFail();
    }

    private function makeTicket(array $overrides = []): Ticket
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();

        return Ticket::query()->create(array_merge([
            'subject' => 'Laptop request',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
        ], $overrides));
    }

    private function catalogItem(array $overrides = []): ServiceCatalogItem
    {
        $category = ServiceCategory::query()->create([
            'name' => 'IT Services',
            'slug' => 'it-services',
            'is_active' => true,
        ]);

        return ServiceCatalogItem::query()->create(array_merge([
            'service_category_id' => $category->id,
            'name' => 'New laptop',
            'slug' => 'new-laptop-'.uniqid(),
            'ticket_type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
            'requires_approval' => true,
            'approver_user_ids' => [$this->admin()->id],
            'is_public' => true,
            'is_active' => true,
        ], $overrides));
    }

    public function test_catalog_approval_starts_pending_request(): void
    {
        $item = $this->catalogItem();

        $ticket = $this->makeTicket(['service_catalog_item_id' => $item->id]);
        $request = app(ApprovalService::class)->startFromCatalog($ticket, $item, $this->agent());

        $this->assertSame(ApprovalRequest::STATUS_PENDING, $request->status);
        $this->assertSame('pending', $ticket->fresh()->status->slug);
        $this->assertCount(1, $request->steps);
    }

    public function test_approver_can_approve_and_open_ticket(): void
    {
        $item = $this->catalogItem([
            'name' => 'Software install',
            'slug' => 'software-install-'.uniqid(),
        ]);

        $ticket = $this->makeTicket(['service_catalog_item_id' => $item->id]);
        $request = app(ApprovalService::class)->startFromCatalog($ticket, $item, $this->agent());

        app(ApprovalService::class)->approve($request->id, $this->admin()->id);

        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame('open', $ticket->fresh()->status->slug);
    }

    public function test_change_ticket_uses_global_approvers(): void
    {
        ServiceDeskSetting::query()->create([
            'change_requires_approval' => true,
            'change_approver_user_ids' => [$this->admin()->id],
        ]);

        $ticket = $this->makeTicket(['type' => ServiceCatalogItem::TYPE_CHANGE]);
        $request = app(ApprovalService::class)->evaluateForNewTicket($ticket, $this->agent()->id);

        $this->assertNotNull($request);
        $this->assertSame(ApprovalRequest::STATUS_PENDING, $request->status);
    }

    public function test_approvals_queue_page_loads_for_enterprise(): void
    {
        $this->actingAs($this->admin())
            ->tenantGet('/service-desk/approvals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ServiceDesk/Approvals'));
    }
}
