<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\Integrations\Models\TicketExternalIssue;
use App\Domains\Integrations\Models\Webhook;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackJiraIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Subscription::query()->create([
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_ACTIVE,
            'renews_at' => now()->addMonth(),
        ]);
    }

    private function createTicket(array $overrides = []): Ticket
    {
        return Ticket::query()->create(array_merge([
            'number' => 'HD-00200',
            'subject' => 'Login failure',
            'ticket_status_id' => TicketStatus::query()->where('slug', 'open')->value('id'),
            'ticket_priority_id' => TicketPriority::query()->where('slug', 'normal')->value('id'),
        ], $overrides));
    }

    public function test_admin_can_configure_slack_integration(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/settings/integrations/slack', [
                'webhook_url' => 'https://hooks.slack.com/services/T00/B00/XXX',
                'channel' => '#support',
                'events' => [Webhook::EVENT_TICKET_CREATED],
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('integration_connections', [
            'provider' => IntegrationConnection::PROVIDER_SLACK,
            'is_active' => true,
        ]);
    }

    public function test_ticket_created_posts_to_slack(): void
    {
        $this->seed(TicketLookupSeeder::class);

        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        IntegrationConnection::query()->create([
            'provider' => IntegrationConnection::PROVIDER_SLACK,
            'config' => [
                'webhook_url' => 'https://hooks.slack.com/services/T00/B00/XXX',
            ],
            'events' => [Webhook::EVENT_TICKET_CREATED],
            'is_active' => true,
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs(User::factory()->create())
            ->post('/tickets', [
                'subject' => 'Slack notify ticket',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/services/T00/B00/XXX'
            && str_contains($request->body(), 'Slack notify ticket'));
    }

    public function test_agent_can_create_jira_issue_for_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);

        Http::fake([
            'https://acme.atlassian.net/rest/api/3/issue' => Http::response([
                'id' => '10001',
                'key' => 'SUP-42',
            ], 201),
        ]);

        IntegrationConnection::query()->create([
            'provider' => IntegrationConnection::PROVIDER_JIRA,
            'config' => [
                'site_url' => 'https://acme.atlassian.net',
                'email' => 'agent@example.com',
                'api_token' => 'secret-token',
                'project_key' => 'SUP',
                'webhook_secret' => 'jira-secret',
            ],
            'is_active' => true,
        ]);

        $ticket = $this->createTicket();
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->post("/tickets/{$ticket->id}/external-issues", [
                'provider' => 'jira',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_external_issues', [
            'ticket_id' => $ticket->id,
            'provider' => IntegrationConnection::PROVIDER_JIRA,
            'external_key' => 'SUP-42',
        ]);
    }

    public function test_jira_webhook_closes_linked_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $ticket = $this->createTicket();
        TicketExternalIssue::query()->create([
            'ticket_id' => $ticket->id,
            'provider' => IntegrationConnection::PROVIDER_JIRA,
            'external_id' => '10001',
            'external_key' => 'SUP-42',
            'external_url' => 'https://acme.atlassian.net/browse/SUP-42',
            'status' => 'In Progress',
        ]);

        IntegrationConnection::query()->create([
            'provider' => IntegrationConnection::PROVIDER_JIRA,
            'config' => ['webhook_secret' => 'jira-secret'],
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/integrations/inbound/jira', [
            'issue' => [
                'id' => '10001',
                'key' => 'SUP-42',
                'fields' => ['status' => ['name' => 'Done']],
            ],
        ], [
            'X-Integration-Secret' => 'jira-secret',
        ])->assertOk();

        $ticket->refresh();
        $this->assertTrue($ticket->status->is_closed);
    }

    public function test_linear_webhook_reopens_linked_ticket(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $ticket = $this->createTicket([
            'ticket_status_id' => TicketStatus::query()->where('slug', 'closed')->value('id'),
        ]);

        TicketExternalIssue::query()->create([
            'ticket_id' => $ticket->id,
            'provider' => IntegrationConnection::PROVIDER_LINEAR,
            'external_id' => 'linear-1',
            'external_key' => 'ENG-9',
            'external_url' => 'https://linear.app/issue/ENG-9',
            'status' => 'Done',
        ]);

        IntegrationConnection::query()->create([
            'provider' => IntegrationConnection::PROVIDER_LINEAR,
            'config' => ['webhook_secret' => 'linear-secret'],
            'is_active' => true,
        ]);

        $payload = json_encode([
            'data' => [
                'id' => 'linear-1',
                'state' => ['name' => 'In Progress'],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'linear-secret');

        $this->call(
            'POST',
            '/api/v1/integrations/inbound/linear',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Linear-Signature' => $signature,
            ],
            $payload,
        )->assertOk();

        $ticket->refresh();
        $this->assertFalse($ticket->status->is_closed);
    }
}
