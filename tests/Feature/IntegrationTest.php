<?php

namespace Tests\Feature;

use App\Domains\Integrations\Models\Webhook;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_integrations_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/integrations')
            ->assertOk();
    }

    public function test_agent_cannot_view_integrations_settings(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/settings/integrations')
            ->assertForbidden();
    }

    public function test_api_can_create_and_list_webhooks(): void
    {
        $admin = User::factory()->admin()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response = $this->withToken($login->json('token'))
            ->postJson('/api/v1/integrations/webhooks', [
                'name' => 'CRM sync',
                'url' => 'https://hooks.example.com/helpdesk',
                'events' => ['ticket.created'],
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'CRM sync')
            ->assertJsonStructure(['secret']);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/integrations/webhooks')
            ->assertOk()
            ->assertJsonFragment(['name' => 'CRM sync']);
    }

    public function test_ticket_created_dispatches_signed_webhook(): void
    {
        $this->seed(TicketLookupSeeder::class);

        Http::fake([
            'https://hooks.example.com/*' => Http::response('ok', 200),
        ]);

        Webhook::query()->create([
            'name' => 'Ticket events',
            'url' => 'https://hooks.example.com/tickets',
            'events' => [Webhook::EVENT_TICKET_CREATED],
            'secret' => 'test-signing-secret',
            'is_active' => true,
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs(User::factory()->create())
            ->post('/tickets', [
                'subject' => 'Webhook test ticket',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://hooks.example.com/tickets') {
                return false;
            }

            $body = $request->body();
            $signature = hash_hmac('sha256', $body, 'test-signing-secret');

            return $request->hasHeader('X-Helpdesk-Event', Webhook::EVENT_TICKET_CREATED)
                && $request->header('X-Helpdesk-Signature')[0] === $signature
                && str_contains($body, 'Webhook test ticket');
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'event' => Webhook::EVENT_TICKET_CREATED,
            'successful' => true,
            'status_code' => 200,
        ]);
    }

    public function test_send_test_delivers_webhook(): void
    {
        Http::fake([
            'https://hooks.example.com/*' => Http::response('ok', 200),
        ]);

        $webhook = Webhook::query()->create([
            'name' => 'Test hook',
            'url' => 'https://hooks.example.com/test',
            'events' => [Webhook::EVENT_TICKET_CREATED],
            'secret' => 'secret-key',
            'is_active' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/settings/integrations/webhooks/{$webhook->id}/test")
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.com/test'
            && $request->hasHeader('X-Helpdesk-Event', Webhook::EVENT_TEST));

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->id,
            'event' => Webhook::EVENT_TEST,
            'successful' => true,
        ]);
    }
}
