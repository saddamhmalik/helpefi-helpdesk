<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\SideConversations\Services\SideConversationThreadService;
use App\Domains\SideConversations\Models\SideConversation;
use App\Domains\SideConversations\Models\SideConversationMessage;
use App\Domains\SideConversations\Services\SideConversationService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SideConversationTest extends TestCase
{
    use RefreshDatabase;

    private function createTicket(array $overrides = []): Ticket
    {
        return Ticket::query()->create(array_merge([
            'number' => 'HD-00050',
            'subject' => 'Printer warranty',
            'ticket_status_id' => TicketStatus::query()->where('slug', 'open')->value('id'),
            'ticket_priority_id' => TicketPriority::query()->where('slug', 'normal')->value('id'),
        ], $overrides));
    }

    public function test_agent_can_start_side_conversation(): void
    {
        Mail::fake();
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $agent = User::factory()->create();
        $ticket = $this->createTicket();

        $this->actingAs($agent)
            ->post("/tickets/{$ticket->id}/side-conversations", [
                'recipient_email' => 'vendor@example.com',
                'recipient_name' => 'Vendor Support',
                'subject' => 'Warranty claim',
                'body' => 'Please confirm coverage for this device.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('side_conversations', [
            'ticket_id' => $ticket->id,
            'recipient_email' => 'vendor@example.com',
            'subject' => 'Warranty claim',
            'status' => SideConversation::STATUS_OPEN,
        ]);

        $conversation = SideConversation::query()->first();
        $this->assertDatabaseHas('side_conversation_messages', [
            'side_conversation_id' => $conversation->id,
            'is_inbound' => false,
        ]);

        $this->assertSame(0, TicketMessage::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_inbound_email_with_side_tag_threads_to_side_conversation(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $ticket = $this->createTicket();
        $agent = User::factory()->create();

        $conversation = app(SideConversationService::class)->create(
            $ticket->id,
            $agent->id,
            'vendor@example.com',
            'Vendor',
            'Warranty claim',
            'Initial outreach',
        );

        Mail::fake();

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'vendor@example.com',
            'from_name' => 'Vendor',
            'subject' => "Re: [{$ticket->number} Side #{$conversation->id}] Warranty claim",
            'body' => 'We can help with that model.',
            'message_id' => 'side-inbound-001',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'side_reply');

        $this->assertDatabaseHas('side_conversation_messages', [
            'side_conversation_id' => $conversation->id,
            'is_inbound' => true,
            'body' => 'We can help with that model.',
        ]);

        $this->assertSame(0, TicketMessage::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_inbound_side_reply_via_in_reply_to_header(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $ticket = $this->createTicket();
        $agent = User::factory()->create();

        $conversation = app(SideConversationService::class)->create(
            $ticket->id,
            $agent->id,
            'partner@example.com',
            null,
            'Parts order',
            'Need replacement part',
        );

        $outbound = SideConversationMessage::query()->where('side_conversation_id', $conversation->id)->first();
        $externalId = SideConversationThreadService::outboundMessageId($conversation->id, $outbound->id);
        $outbound->update(['external_id' => $externalId]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'partner@example.com',
            'subject' => 'Re: Parts order',
            'body' => 'Part is in stock.',
            'message_id' => 'side-inbound-002',
            'in_reply_to' => ["<{$externalId}>"],
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'side_reply');

        $this->assertDatabaseHas('side_conversation_messages', [
            'side_conversation_id' => $conversation->id,
            'is_inbound' => true,
            'body' => 'Part is in stock.',
        ]);
    }

    public function test_side_conversation_does_not_merge_into_main_ticket_thread(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');
        $ticket = $this->createTicket(['channel_id' => $emailChannelId]);
        $agent = User::factory()->create();

        $conversation = app(SideConversationService::class)->create(
            $ticket->id,
            $agent->id,
            'vendor@example.com',
            null,
            'Vendor follow-up',
            'Checking status',
        );

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'vendor@example.com',
            'subject' => "Re: [{$ticket->number} Side #{$conversation->id}] Vendor follow-up",
            'body' => 'Still investigating.',
            'message_id' => 'side-inbound-003',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'side_reply');

        $this->assertSame(0, TicketMessage::query()->where('ticket_id', $ticket->id)->count());
    }
}
