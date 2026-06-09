<?php

namespace Tests\Feature;

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Automation\Models\AutomationScheduledAction;
use App\Domains\Channels\Models\Channel;
use App\Domains\Contacts\Models\Tag;
use App\Domains\Integrations\Models\Webhook;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_rule_adds_tag_and_priority(): void
    {
        $this->seed(TicketLookupSeeder::class);

        $urgent = TicketPriority::query()->where('slug', 'urgent')->first()
            ?? TicketPriority::query()->where('slug', 'high')->first();

        AutomationRule::query()->create([
            'name' => 'Refund escalation',
            'trigger' => AutomationRule::TRIGGER_TICKET_CREATED,
            'conditions' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'refund'],
            ],
            'actions' => [
                ['type' => 'set_priority', 'value' => $urgent->id],
                ['type' => 'add_tag', 'value' => 'billing'],
            ],
            'is_active' => true,
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs(User::factory()->create())
            ->post('/tickets', [
                'subject' => 'Need a refund please',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Need a refund please')->firstOrFail();

        $this->assertSame($urgent->id, $ticket->ticket_priority_id);
        $this->assertTrue($ticket->tags()->where('slug', 'billing')->exists());
    }

    public function test_delayed_actions_run_in_chain(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        $this->seed(TicketLookupSeeder::class);

        AutomationRule::query()->create([
            'name' => 'Follow up later',
            'trigger' => AutomationRule::TRIGGER_TICKET_CREATED,
            'conditions' => [],
            'actions' => [
                ['type' => 'delay', 'minutes' => 30],
                ['type' => 'add_internal_note', 'value' => 'Delayed follow-up note'],
            ],
            'is_active' => true,
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $this->actingAs(User::factory()->create())
            ->post('/tickets', [
                'subject' => 'Delayed workflow',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ]);

        $ticket = Ticket::query()->where('subject', 'Delayed workflow')->firstOrFail();

        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Delayed follow-up note',
        ]);

        $this->assertDatabaseHas('automation_scheduled_actions', [
            'ticket_id' => $ticket->id,
        ]);

        Carbon::setTestNow('2026-01-01 10:31:00');

        $this->artisan('automation:process-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Delayed follow-up note',
            'is_internal' => true,
        ]);
    }

    public function test_automation_can_send_webhook_action(): void
    {
        Http::fake(['https://hooks.example.test/*' => Http::response(['ok' => true], 200)]);

        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $webhook = Webhook::query()->create([
            'name' => 'Automation hook',
            'url' => 'https://hooks.example.test/automation',
            'events' => [Webhook::EVENT_TICKET_CREATED],
            'secret' => 'secret-key',
            'is_active' => true,
        ]);

        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00100',
            'subject' => 'Webhook test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
        ]);

        AutomationRule::query()->create([
            'name' => 'Webhook on reply',
            'trigger' => AutomationRule::TRIGGER_CUSTOMER_MESSAGE,
            'conditions' => [
                ['field' => 'message_body', 'operator' => 'contains', 'value' => 'escalate'],
            ],
            'actions' => [
                ['type' => 'send_webhook', 'value' => $webhook->id],
            ],
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'subject' => 'Re: [HD-00100] Webhook test',
            'body' => 'Please escalate this issue',
            'message_id' => 'auto-v2-test-1',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/automation');
    }

    public function test_automation_meta_includes_v2_actions(): void
    {
        $admin = User::factory()->admin()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/automation/meta')
            ->assertOk()
            ->assertJsonFragment(['value' => 'add_tag'])
            ->assertJsonFragment(['value' => 'send_webhook'])
            ->assertJsonFragment(['value' => 'delay'])
            ->assertJsonFragment(['value' => 'message_body']);
    }
}
