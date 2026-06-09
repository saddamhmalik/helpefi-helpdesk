<?php

namespace Tests\Feature;

use App\Domains\Channels\Jobs\SendAutoFirstResponseJob;
use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Settings\Models\HelpdeskSetting;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboundEmailPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);
    }

    public function test_blocked_email_does_not_create_ticket(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'email_blocklist' => ['blocked@example.com'],
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'blocked@example.com',
            'from_name' => 'Blocked Sender',
            'subject' => 'Should be ignored',
            'body' => 'This should not create a ticket.',
            'message_id' => 'blocked-msg-001',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()
            ->assertJsonPath('action', 'blocked');

        $this->assertDatabaseMissing('tickets', ['subject' => 'Should be ignored']);
    }

    public function test_blocked_domain_does_not_create_ticket(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'email_blocklist' => ['spam.net'],
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'user@spam.net',
            'subject' => 'Spam request',
            'body' => 'Ignore me.',
            'message_id' => 'blocked-domain-001',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()
            ->assertJsonPath('action', 'blocked');

        $this->assertDatabaseMissing('tickets', ['subject' => 'Spam request']);
    }

    public function test_blocked_email_does_not_reply_to_existing_ticket(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'email_blocklist' => ['blocked@example.com'],
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-00099',
            'subject' => 'Open ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'blocked@example.com',
            'subject' => 'Re: [HD-00099] Open ticket',
            'body' => 'Blocked follow-up.',
            'message_id' => 'blocked-reply-001',
            'ticket_number' => 'HD-00099',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()
            ->assertJsonPath('action', 'blocked');

        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Blocked follow-up.',
        ]);
    }

    public function test_auto_first_response_is_added_when_ticket_is_created_from_email(): void
    {
        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'auto_first_response_enabled' => true,
            'auto_first_response_body' => 'Hello {{contact_name}}, ticket [{{ticket_number}}] received.',
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'subject' => 'Need help',
            'body' => 'Please assist.',
            'message_id' => 'auto-response-001',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertCreated();

        $ticket = Ticket::query()->where('subject', 'Need help')->first();

        $this->assertSame(2, $ticket->messages()->count());

        $messages = $ticket->messages()->orderBy('created_at')->orderBy('id')->get();
        $this->assertNotNull($messages[0]->contact_id);
        $this->assertNull($messages[1]->contact_id);
        $this->assertNull($messages[1]->user_id);
        $this->assertTrue(
            $ticket->messages()
                ->where('body', 'like', '%Hello Customer, ticket ['.$ticket->number.'] received.%')
                ->exists()
        );
    }

    public function test_auto_first_response_queues_email_when_outbound_is_enabled(): void
    {
        Queue::fake();
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        HelpdeskSetting::query()->create([
            'ticket_number_prefix' => 'HD-',
            'auto_first_response_enabled' => true,
            'auto_first_response_body' => 'Thanks, your ticket is [{{ticket_number}}].',
        ]);

        MailSetting::query()->first()->update([
            'enabled' => true,
            'reply_enabled' => true,
            'driver' => 'log',
            'from_address' => 'support@helpdesk.test',
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');

        $ticket = Ticket::query()->create([
            'number' => 'HD-00100',
            'subject' => 'Email ticket',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
            'email_inbox_id' => \App\Domains\Channels\Models\EmailInbox::query()->value('id'),
            'contact_id' => \App\Domains\Contacts\Models\Contact::query()->create([
                'name' => 'Customer',
                'email' => 'customer@example.com',
            ])->id,
        ]);

        app(\App\Domains\Tickets\Services\TicketService::class)->addContactMessage(
            $ticket->id,
            $ticket->contact_id,
            'Initial customer email.',
            $emailChannelId,
            'customer-msg-001',
        );

        $ticket->refresh();

        Queue::assertPushed(SendAutoFirstResponseJob::class, function (SendAutoFirstResponseJob $job) use ($ticket) {
            return $job->ticketId === $ticket->id;
        });
    }
}
