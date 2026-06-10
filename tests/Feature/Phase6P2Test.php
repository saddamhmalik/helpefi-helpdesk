<?php

namespace Tests\Feature;

use App\Domains\Ai\Models\AiSetting;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

class Phase6P2Test extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
            ChannelSeeder::class,
            SlaSeeder::class,
        ]);

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

    public function test_admin_can_manage_ticket_statuses(): void
    {
        $this->actingAs($this->admin())
            ->tenantPost('/settings/ticket-statuses', [
                'name' => 'Awaiting vendor',
                'color' => 'purple',
                'is_closed' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_statuses', [
            'name' => 'Awaiting vendor',
            'slug' => 'awaiting-vendor',
        ]);

        $status = TicketStatus::query()->where('slug', 'awaiting-vendor')->firstOrFail();

        $this->actingAs($this->admin())
            ->tenantPut("/settings/ticket-statuses/{$status->id}", [
                'name' => 'Waiting on vendor',
                'color' => 'orange',
                'is_closed' => false,
                'sort_order' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_statuses', [
            'id' => $status->id,
            'name' => 'Waiting on vendor',
        ]);
    }

    public function test_agent_can_snooze_and_unsnooze_ticket(): void
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $contact = Contact::query()->create(['name' => 'Pat', 'email' => 'pat@example.com']);

        $ticket = Ticket::query()->create([
            'subject' => 'Snooze me',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($this->admin())
            ->tenantPostJson("/workspace/tickets/{$ticket->id}/snooze", ['minutes' => 60])
            ->assertOk()
            ->assertJsonPath('snoozed_until', fn ($value) => $value !== null);

        $ticket->refresh();
        $this->assertNotNull($ticket->snoozed_until);
        $this->assertTrue($ticket->isSnoozed());

        $this->actingAs($this->admin())
            ->tenantDeleteJson("/workspace/tickets/{$ticket->id}/snooze")
            ->assertOk();

        $ticket->refresh();
        $this->assertNull($ticket->snoozed_until);
    }

    public function test_snoozed_tickets_are_hidden_from_workspace_queue(): void
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $contact = Contact::query()->create(['name' => 'Hidden', 'email' => 'hidden@example.com']);

        $ticket = Ticket::query()->create([
            'subject' => 'Hidden while snoozed',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'snoozed_until' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->admin())
            ->tenantGet('/workspace')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['queue']['data'] ?? [])
            ->pluck('id');

        $this->assertFalse($ids->contains($ticket->id));
    }

    public function test_admin_can_enable_ai_triage(): void
    {
        $this->actingAs($this->admin())
            ->tenantPut('/settings/ai', [
                'enabled' => true,
                'model' => '',
                'triage_enabled' => true,
                'deflection_enabled' => false,
                'deflection_portal_enabled' => true,
                'deflection_widget_enabled' => true,
            ])
            ->assertRedirect();

        $setting = AiSetting::query()->first();
        $this->assertTrue($setting->triage_enabled);
    }

    public function test_ticket_create_dispatches_crm_and_triage_jobs(): void
    {
        Queue::fake();

        IntegrationConnection::query()->create([
            'provider' => IntegrationConnection::PROVIDER_HUBSPOT,
            'is_active' => true,
            'config' => ['access_token' => 'test-token'],
        ]);

        AiSetting::query()->updateOrCreate([], [
            'enabled' => true,
            'triage_enabled' => true,
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();

        $this->actingAs($this->admin())
            ->tenantPost('/tickets', [
                'subject' => 'Urgent outage',
                'description' => 'Production is down',
                'contact_email' => 'crm@example.com',
                'contact_name' => 'CRM User',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        Queue::assertPushed(\App\Domains\Integrations\Jobs\EnrichTicketFromCrmJob::class);
        Queue::assertPushed(\App\Domains\Ai\Jobs\TriageTicketJob::class);
    }
}
