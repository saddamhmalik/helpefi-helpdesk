<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_channels_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/channels')
            ->assertOk();
    }

    public function test_agent_cannot_view_channels_settings(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get('/settings/channels')
            ->assertForbidden();
    }

    public function test_updating_chat_channel_preserves_widget_key(): void
    {
        $this->seed(ChannelSeeder::class);

        $admin = User::factory()->admin()->create();
        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();
        $widgetKey = $channel->settings['widget_key'];

        $this->actingAs($admin)
            ->put("/settings/channels/{$channel->id}", [
                'is_active' => true,
                'settings' => [
                    'greeting' => 'Updated greeting',
                    'offline_message' => $channel->settings['offline_message'],
                    'offline_mode' => 'never',
                    'allowed_origins' => ['*'],
                ],
            ])
            ->assertRedirect();

        $channel->refresh();

        $this->assertSame($widgetKey, $channel->settings['widget_key']);
        $this->assertSame('Updated greeting', $channel->settings['greeting']);
        $this->assertSame('never', $channel->settings['offline_mode']);
    }

    public function test_updating_chat_channel_without_widget_key_generates_one(): void
    {
        $this->seed(ChannelSeeder::class);

        $admin = User::factory()->admin()->create();
        $channel = Channel::query()->where('slug', 'chat')->firstOrFail();
        $settings = $channel->settings;
        unset($settings['widget_key']);
        $channel->update(['settings' => $settings]);

        $this->actingAs($admin)
            ->put("/settings/channels/{$channel->id}", [
                'is_active' => true,
                'settings' => [
                    'greeting' => $settings['greeting'],
                    'offline_message' => $settings['offline_message'],
                    'offline_mode' => $settings['offline_mode'],
                    'allowed_origins' => $settings['allowed_origins'],
                ],
            ])
            ->assertRedirect();

        $channel->refresh();

        $this->assertNotEmpty($channel->settings['widget_key']);
        $this->assertSame(32, strlen($channel->settings['widget_key']));
    }

    public function test_inbound_email_creates_ticket(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $response = $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'sender@example.com',
            'from_name' => 'Sender',
            'subject' => 'Need help with billing',
            'body' => 'I was charged twice.',
            'message_id' => 'msg-001',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ]);

        $response->assertCreated()
            ->assertJsonPath('action', 'created');

        $this->assertDatabaseHas('tickets', ['subject' => 'Need help with billing']);
        $this->assertDatabaseHas('ticket_messages', [
            'body' => 'I was charged twice.',
            'external_id' => 'msg-001',
        ]);

        $ticket = Ticket::query()->where('subject', 'Need help with billing')->first();
        $this->assertSame(Channel::query()->where('slug', 'email')->value('id'), $ticket->channel_id);
    }

    public function test_inbound_email_replies_to_existing_ticket_by_subject_tag(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-00042',
            'subject' => 'Billing issue',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'sender@example.com',
            'subject' => 'Re: [HD-00042] Billing issue',
            'body' => 'Any update?',
            'message_id' => 'msg-002',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'reply');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Any update?',
        ]);
    }

    public function test_inbound_email_replies_via_in_reply_to_header(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');
        $inboxId = \App\Domains\Channels\Models\EmailInbox::query()->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-00099',
            'subject' => 'Login issue',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
            'email_inbox_id' => $inboxId,
        ]);

        \App\Domains\Tickets\Models\TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'body' => 'Original request',
            'is_internal' => false,
            'channel_id' => $emailChannelId,
            'external_id' => 'original-msg-id@example.com',
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'sender@example.com',
            'subject' => 'Re: Login issue',
            'body' => 'Still broken.',
            'message_id' => 'reply-msg-id@example.com',
            'in_reply_to' => ['original-msg-id@example.com'],
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'reply');

        $this->assertSame(1, Ticket::query()->where('subject', 'Login issue')->count());
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Still broken.',
        ]);
    }

    public function test_inbound_email_falls_back_to_thread_headers_when_subject_ticket_number_is_missing(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');
        $inboxId = \App\Domains\Channels\Models\EmailInbox::query()->value('id');
        $contactEmail = 'sender@example.com';

        $ticket = Ticket::query()->create([
            'number' => 'HD-00002',
            'subject' => 'Creating a new ticket with sla',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
            'email_inbox_id' => $inboxId,
            'contact_id' => \App\Domains\Contacts\Models\Contact::query()->create([
                'name' => 'Sender',
                'email' => $contactEmail,
            ])->id,
        ]);

        \App\Domains\Tickets\Models\TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'body' => 'Original request',
            'is_internal' => false,
            'channel_id' => $emailChannelId,
            'external_id' => 'original-gmail-msg@mail.gmail.com',
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => $contactEmail,
            'subject' => 'Re: [HD-00099] Creating a new ticket with sla',
            'body' => 'Follow up in the same thread.',
            'message_id' => 'reply-gmail-msg@mail.gmail.com',
            'in_reply_to' => ['original-gmail-msg@mail.gmail.com'],
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'reply');

        $this->assertSame(1, Ticket::query()->where('subject', 'Creating a new ticket with sla')->count());
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Follow up in the same thread.',
        ]);
    }

    public function test_inbound_email_does_not_create_duplicate_ticket_for_same_message_id(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $payload = [
            'from_email' => 'sender@example.com',
            'subject' => 'First contact',
            'body' => 'Hello',
            'message_id' => 'duplicate-msg-id@example.com',
        ];

        $this->postJson('/api/v1/channels/inbound/email', $payload, [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertCreated();

        $this->postJson('/api/v1/channels/inbound/email', $payload, [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'duplicate');

        $this->assertSame(1, Ticket::query()->where('subject', 'First contact')->count());
    }

    public function test_inbound_email_rejects_invalid_token(): void
    {
        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'sender@example.com',
            'body' => 'Hello',
        ], [
            'X-Channel-Token' => 'wrong-token',
        ])->assertUnprocessable();
    }

    public function test_portal_ticket_uses_portal_channel(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $this->post('/portal/submit', [
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'subject' => 'Portal channel test',
            'description' => 'From portal form',
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Portal channel test')->first();
        $this->assertSame(Channel::query()->where('slug', 'portal')->value('id'), $ticket->channel_id);
    }
}
