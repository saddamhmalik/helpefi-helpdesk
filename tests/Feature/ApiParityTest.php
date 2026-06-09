<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Sla\Models\SlaEscalationRule;
use App\Domains\Sla\Models\SlaPolicy;
use App\Domains\Tickets\Jobs\SendTicketExportJob;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function apiToken(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');
    }

    public function test_api_lists_contacts_with_stats(): void
    {
        Contact::query()->create(['name' => 'Alex', 'email' => 'alex@example.com']);

        $token = $this->apiToken(User::factory()->admin()->create());

        $this->withToken($token)
            ->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonFragment(['email' => 'alex@example.com']);
    }

    public function test_api_can_create_and_show_contact(): void
    {
        $token = $this->apiToken(User::factory()->admin()->create());

        $create = $this->withToken($token)
            ->postJson('/api/v1/contacts', [
                'name' => 'Jamie',
                'email' => 'jamie@example.com',
            ])
            ->assertCreated()
            ->json();

        $this->withToken($token)
            ->getJson('/api/v1/contacts/'.$create['id'])
            ->assertOk()
            ->assertJsonPath('email', 'jamie@example.com');
    }

    public function test_api_can_search_contacts(): void
    {
        Contact::query()->create(['name' => 'Searchable Person', 'email' => 'search@example.com']);

        $token = $this->apiToken(User::factory()->admin()->create());

        $this->withToken($token)
            ->getJson('/api/v1/contacts/search?q=Searchable')
            ->assertOk()
            ->assertJsonPath('results.0.email', 'search@example.com');
    }

    public function test_api_can_add_contact_note(): void
    {
        $contact = Contact::query()->create(['name' => 'Alex', 'email' => 'alex@example.com']);
        $token = $this->apiToken(User::factory()->admin()->create());

        $this->withToken($token)
            ->postJson("/api/v1/contacts/{$contact->id}/notes", [
                'body' => 'VIP customer',
            ])
            ->assertCreated()
            ->assertJsonPath('body', 'VIP customer');
    }

    public function test_api_can_manage_organizations(): void
    {
        $token = $this->apiToken(User::factory()->admin()->create());

        $create = $this->withToken($token)
            ->postJson('/api/v1/organizations', [
                'name' => 'Acme Corp',
                'domains' => ['acme.com'],
            ])
            ->assertCreated()
            ->json();

        $this->withToken($token)
            ->getJson('/api/v1/organizations/'.$create['id'])
            ->assertOk()
            ->assertJsonPath('name', 'Acme Corp')
            ->assertJsonPath('domains.0.domain', 'acme.com');

        $this->withToken($token)
            ->putJson('/api/v1/organizations/'.$create['id'], [
                'name' => 'Acme International',
                'domains' => ['acme.com', 'acme.io'],
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Acme International');

        $this->withToken($token)
            ->delete('/api/v1/organizations/'.$create['id'])
            ->assertOk();

        $this->assertDatabaseMissing('organizations', ['id' => $create['id']]);
    }

    public function test_api_can_download_ticket_pdf(): void
    {
        $user = User::factory()->create();
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);
        $ticket = Ticket::query()->create([
            'number' => 'HD-API01',
            'subject' => 'API export',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $token = $this->apiToken($user);

        $response = $this->withToken($token)
            ->get("/api/v1/tickets/{$ticket->id}/export/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_api_can_queue_ticket_export_email(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);
        $ticket = Ticket::query()->create([
            'number' => 'HD-API02',
            'subject' => 'API email export',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $token = $this->apiToken($user);

        $this->withToken($token)
            ->postJson("/api/v1/tickets/{$ticket->id}/export/email", [
                'email' => 'recipient@example.com',
                'include_conversation' => true,
            ])
            ->assertAccepted()
            ->assertJsonPath('email', 'recipient@example.com');

        Queue::assertPushed(SendTicketExportJob::class, fn (SendTicketExportJob $job) => $job->ticketId === $ticket->id);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->getJson('/api/v1/organizations')->assertUnauthorized();
        $this->getJson('/api/v1/sla/escalations')->assertUnauthorized();
        $this->getJson('/api/v1/settings/helpdesk')->assertUnauthorized();
        $this->getJson('/api/v1/search')->assertUnauthorized();
    }

    public function test_api_can_manage_sla_escalation_rules(): void
    {
        $this->seed([TicketLookupSeeder::class, SlaSeeder::class]);

        $policy = SlaPolicy::query()->where('is_default', true)->firstOrFail();
        $token = $this->apiToken(User::factory()->admin()->create());

        $this->withToken($token)
            ->getJson('/api/v1/sla/escalations/meta')
            ->assertOk()
            ->assertJsonPath('levels.0.value', 1)
            ->assertJsonPath('breach_types.0.value', SlaEscalationRule::BREACH_FIRST_RESPONSE);

        $create = $this->withToken($token)
            ->postJson('/api/v1/sla/escalations', [
                'sla_policy_id' => $policy->id,
                'level' => 1,
                'breach_type' => SlaEscalationRule::BREACH_FIRST_RESPONSE,
                'delay_minutes_after_breach' => 15,
                'actions' => [
                    ['type' => 'add_internal_note', 'value' => 'SLA breached'],
                ],
            ])
            ->assertCreated()
            ->json();

        $this->withToken($token)
            ->getJson('/api/v1/sla/escalations')
            ->assertOk()
            ->assertJsonPath('rules.0.id', $create['id'])
            ->assertJsonPath('rules.0.policy_name', 'Default SLA');

        $this->withToken($token)
            ->delete('/api/v1/sla/escalations/'.$create['id'])
            ->assertOk();

        $this->assertDatabaseMissing('sla_escalation_rules', ['id' => $create['id']]);
    }

    public function test_api_can_read_and_update_helpdesk_settings(): void
    {
        $token = $this->apiToken(User::factory()->admin()->create());

        $this->withToken($token)
            ->getJson('/api/v1/settings/helpdesk')
            ->assertOk()
            ->assertJsonPath('ticket_number_prefix', 'HD-');

        $this->withToken($token)
            ->putJson('/api/v1/settings/helpdesk', [
                'ticket_number_prefix' => 'SUP',
                'auto_first_response_enabled' => true,
                'email_blocklist' => ['spam.example.com'],
            ])
            ->assertOk()
            ->assertJsonPath('ticket_number_prefix', 'SUP-')
            ->assertJsonPath('auto_first_response_enabled', true)
            ->assertJsonPath('email_blocklist.0', 'spam.example.com');
    }

    public function test_api_global_search_returns_grouped_results(): void
    {
        Contact::query()->create(['name' => 'Global Search Person', 'email' => 'global-search@example.com']);
        Organization::query()->create(['name' => 'Global Search Org']);

        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);
        Ticket::query()->create([
            'number' => 'HD-GLOBAL',
            'subject' => 'Global search ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $token = $this->apiToken(User::factory()->admin()->create());

        $this->withToken($token)
            ->getJson('/api/v1/search?q=Global')
            ->assertOk()
            ->assertJsonPath('query', 'Global')
            ->assertJsonFragment(['type' => 'tickets'])
            ->assertJsonFragment(['type' => 'contacts'])
            ->assertJsonFragment(['type' => 'organizations']);
    }
}
