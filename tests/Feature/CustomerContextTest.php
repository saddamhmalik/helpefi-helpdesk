<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Integrations\Models\ContactCrmProfile;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Services\CrmProfileService;
use App\Domains\Sla\Models\TicketSlaTimer;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TenantTestCase;

class CustomerContextTest extends TenantTestCase
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

    }

    private function admin()
    {
        return \App\Models\User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    private function createTicket(Contact $contact, string $subject = 'Support request'): Ticket
    {
        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();

        return Ticket::query()->create([
            'subject' => $subject,
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);
    }

    public function test_customer_context_endpoint_returns_account_metrics(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme Corp',
            'customer_tier' => 'enterprise',
        ]);

        $contact = Contact::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'organization_id' => $organization->id,
        ]);

        $ticket = $this->createTicket($contact);
        $closed = TicketStatus::query()->where('is_closed', true)->firstOrFail();

        Ticket::query()->create([
            'subject' => 'Closed ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $closed->id,
            'ticket_priority_id' => $ticket->ticket_priority_id,
        ]);

        CsatResponse::query()->create([
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'rating' => 5,
            'channel' => CsatResponse::CHANNEL_PORTAL,
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'body' => 'Need help',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($this->admin())
            ->tenantGetJson("/tickets/{$ticket->id}/customer-context")
            ->assertOk()
            ->assertJsonPath('scope', 'organization')
            ->assertJsonPath('organization.name', 'Acme Corp')
            ->assertJsonPath('organization.customer_tier', 'enterprise')
            ->assertJsonPath('metrics.open_tickets', 1)
            ->assertJsonPath('metrics.total_tickets', 2)
            ->assertJsonPath('metrics.csat_average_90d', 5)
            ->assertJsonPath('health.level', 'healthy');
    }

    public function test_account_health_drops_with_breaches_and_low_csat(): void
    {
        $contact = Contact::query()->create([
            'name' => 'Risky User',
            'email' => 'risk@example.com',
        ]);

        $ticket = $this->createTicket($contact, 'Escalation');

        Ticket::query()->create([
            'subject' => 'Another open ticket',
            'contact_id' => $contact->id,
            'ticket_status_id' => $ticket->ticket_status_id,
            'ticket_priority_id' => $ticket->ticket_priority_id,
        ]);

        TicketSlaTimer::query()->create([
            'ticket_id' => $ticket->id,
            'first_response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->addDay(),
            'first_response_breached' => true,
            'resolution_breached' => false,
            'updated_at' => now(),
        ]);

        CsatResponse::query()->create([
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'rating' => 2,
            'channel' => CsatResponse::CHANNEL_EMAIL,
        ]);

        $response = $this->actingAs($this->admin())
            ->tenantGetJson("/tickets/{$ticket->id}/customer-context")
            ->assertOk();

        $this->assertLessThan(80, $response->json('health.score'));
        $this->assertContains($response->json('health.level'), ['at_risk', 'critical']);
    }

    public function test_customer_context_includes_cached_crm_profile(): void
    {
        $contact = Contact::query()->create([
            'name' => 'CRM User',
            'email' => 'crm@hubspot.test',
        ]);

        $ticket = $this->createTicket($contact);

        ContactCrmProfile::query()->create([
            'contact_id' => $contact->id,
            'provider' => IntegrationConnection::PROVIDER_HUBSPOT,
            'external_id' => '12345',
            'profile' => [
                'name' => 'CRM User',
                'company' => 'HubSpot Co',
                'lifecycle_stage' => 'customer',
                'deal_value' => 25000,
                'owner' => 'Alex Rep',
                'url' => 'https://app.hubspot.com/contacts/12345',
            ],
            'synced_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->tenantGetJson("/tickets/{$ticket->id}/customer-context")
            ->assertOk()
            ->assertJsonPath('crm.provider', 'hubspot')
            ->assertJsonPath('crm.company', 'HubSpot Co')
            ->assertJsonPath('crm.lifecycle_stage', 'customer')
            ->assertJsonPath('crm.deal_value', 25000)
            ->assertJsonPath('crm.owner', 'Alex Rep');
    }

    public function test_crm_sync_updates_contact_fields_when_empty(): void
    {
        IntegrationConnection::query()->create([
            'provider' => IntegrationConnection::PROVIDER_HUBSPOT,
            'is_active' => true,
            'config' => ['access_token' => 'test-token'],
        ]);

        $contact = Contact::query()->create([
            'name' => '',
            'email' => 'sync@example.com',
        ]);

        $hubspot = Mockery::mock(\App\Domains\Integrations\Services\HubspotIntegrationService::class);
        $hubspot->shouldReceive('lookupContactByEmail')
            ->once()
            ->with('sync@example.com')
            ->andReturn([
                'provider' => IntegrationConnection::PROVIDER_HUBSPOT,
                'id' => '999',
                'profile' => [
                    'name' => 'Synced Name',
                    'email' => 'sync@example.com',
                    'phone' => '+15551212',
                    'company' => 'Synced Co',
                    'url' => 'https://app.hubspot.com/contacts/999',
                ],
            ]);

        $salesforce = Mockery::mock(\App\Domains\Integrations\Services\SalesforceIntegrationService::class);
        $this->app->instance(\App\Domains\Integrations\Services\HubspotIntegrationService::class, $hubspot);
        $this->app->instance(\App\Domains\Integrations\Services\SalesforceIntegrationService::class, $salesforce);

        $profile = $this->app->make(CrmProfileService::class)->syncForContact($contact);

        $this->assertNotNull($profile);
        $this->assertDatabaseHas('contact_crm_profiles', [
            'contact_id' => $contact->id,
            'provider' => 'hubspot',
            'external_id' => '999',
        ]);

        $contact->refresh();
        $this->assertSame('Synced Name', $contact->name);
        $this->assertSame('+15551212', $contact->phone);
        $this->assertSame('Synced Co', $contact->company);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
