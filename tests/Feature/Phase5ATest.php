<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Services\Mailbox\InboundMailParser;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Macros\Models\CannedResponse;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketCc;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Phase5ATest extends TestCase
{
    use RefreshDatabase;

    private function seedTicketMeta(): array
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        return [$status, $priority];
    }

    private function createTicket(array $overrides = []): Ticket
    {
        [$status, $priority] = $this->seedTicketMeta();

        return Ticket::query()->create(array_merge([
            'number' => 'HD-00001',
            'subject' => 'Phase 5 test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ], $overrides));
    }

    public function test_agent_can_create_macro_and_apply_with_placeholders(): void
    {
        $user = User::factory()->create(['name' => 'Agent Smith']);
        $contact = Contact::query()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $ticket = $this->createTicket(['contact_id' => $contact->id, 'number' => 'HD-00099']);

        $this->actingAs($user)
            ->post('/settings/macros', [
                'title' => 'Greeting',
                'shortcut' => 'hello',
                'body' => 'Hi {{contact.name}}, regarding {{ticket.number}} — {{agent.name}}',
                'is_shared' => false,
            ])
            ->assertRedirect();

        $macro = CannedResponse::query()->where('title', 'Greeting')->first();
        $this->assertNotNull($macro);

        $response = $this->actingAs($user)
            ->postJson("/canned-responses/{$macro->id}/apply", [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $this->assertSame(
            'Hi Jane Doe, regarding HD-00099 — Agent Smith',
            $response->json('body'),
        );
    }

    public function test_macro_search_returns_accessible_responses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        CannedResponse::query()->create([
            'title' => 'My macro',
            'shortcut' => 'mine',
            'body' => 'Personal reply',
            'is_shared' => false,
            'user_id' => $user->id,
        ]);

        CannedResponse::query()->create([
            'title' => 'Shared macro',
            'shortcut' => 'shared',
            'body' => 'Team reply',
            'is_shared' => true,
            'user_id' => $other->id,
        ]);

        CannedResponse::query()->create([
            'title' => 'Private other',
            'shortcut' => 'private',
            'body' => 'Hidden',
            'is_shared' => false,
            'user_id' => $other->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/canned-responses/search?q=macro')
            ->assertOk();

        $titles = collect($response->json('results'))->pluck('title')->all();

        $this->assertContains('My macro', $titles);
        $this->assertContains('Shared macro', $titles);
        $this->assertNotContains('Private other', $titles);
    }

    public function test_presence_tracks_viewers_and_composing_state(): void
    {
        Cache::flush();

        $viewer = User::factory()->create(['name' => 'Viewer One']);
        $composer = User::factory()->create(['name' => 'Composer Two']);
        $ticket = $this->createTicket();

        $this->actingAs($viewer)
            ->postJson("/workspace/tickets/{$ticket->id}/presence", ['composing' => false])
            ->assertOk();

        $this->actingAs($composer)
            ->postJson("/workspace/tickets/{$ticket->id}/presence", ['composing' => true])
            ->assertOk();

        $response = $this->actingAs($viewer)
            ->getJson("/workspace/tickets/{$ticket->id}/poll")
            ->assertOk();

        $viewers = collect($response->json('viewers'));
        $this->assertTrue($viewers->contains(fn (array $entry) => $entry['name'] === 'Composer Two' && $entry['composing']));
        $this->assertFalse($viewers->contains(fn (array $entry) => $entry['name'] === 'Viewer One'));

        $this->actingAs($composer)
            ->deleteJson("/workspace/tickets/{$ticket->id}/presence")
            ->assertOk();

        $response = $this->actingAs($viewer)
            ->getJson("/workspace/tickets/{$ticket->id}/poll")
            ->assertOk();

        $this->assertEmpty($response->json('viewers'));
    }

    public function test_poll_pulse_detects_ticket_changes(): void
    {
        Cache::flush();

        $viewer = User::factory()->create();
        $replier = User::factory()->create();
        $ticket = $this->createTicket();

        $initial = $this->actingAs($viewer)
            ->getJson("/workspace/tickets/{$ticket->id}/poll")
            ->assertOk();

        $pulse = $initial->json('pulse');
        $this->assertNotNull($pulse);

        $this->travel(2)->seconds();

        $this->actingAs($replier)
            ->postJson("/workspace/tickets/{$ticket->id}/reply", [
                'body' => 'Update from another agent',
                'is_internal' => false,
            ])
            ->assertOk();

        $followUp = $this->actingAs($viewer)
            ->getJson("/workspace/tickets/{$ticket->id}/poll?pulse={$pulse}")
            ->assertOk();

        $this->assertTrue($followUp->json('ticket_changed'));
    }

    public function test_inbound_email_syncs_cc_emails_to_ticket(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'sender@example.com',
            'from_name' => 'Sender',
            'subject' => 'Issue with CC',
            'body' => 'Please loop in my manager.',
            'message_id' => 'msg-cc-001',
            'cc_emails' => ['manager@example.com', 'billing@example.com'],
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertCreated();

        $ticket = Ticket::query()->where('subject', 'Issue with CC')->first();
        $this->assertNotNull($ticket);

        $this->assertDatabaseHas('ticket_ccs', [
            'ticket_id' => $ticket->id,
            'email' => 'manager@example.com',
        ]);
        $this->assertDatabaseHas('ticket_ccs', [
            'ticket_id' => $ticket->id,
            'email' => 'billing@example.com',
        ]);
    }

    public function test_cc_participant_can_reply_via_re_subject(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $emailChannelId = Channel::query()->where('slug', 'email')->value('id');

        $contact = Contact::query()->create(['name' => 'Requester', 'email' => 'requester@example.com']);

        $ticket = Ticket::query()->create([
            'number' => 'HD-00055',
            'subject' => 'Shared project update',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
            'channel_id' => $emailChannelId,
            'contact_id' => $contact->id,
        ]);

        TicketCc::query()->create([
            'ticket_id' => $ticket->id,
            'email' => 'cc.participant@example.com',
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'cc.participant@example.com',
            'from_name' => 'CC Participant',
            'subject' => 'Re: Shared project update',
            'body' => 'Adding my input from CC.',
            'message_id' => 'msg-cc-reply-001',
        ], [
            'X-Channel-Token' => 'dev-inbound-token',
        ])->assertOk()->assertJsonPath('action', 'reply');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Adding my input from CC.',
        ]);
    }

    public function test_mail_parser_extracts_cc_header(): void
    {
        $raw = <<<'MAIL'
From: sender@example.com
To: support@helpdesk.test
Cc: Manager <manager@example.com>, billing@example.com
Subject: CC test
Message-ID: <cc-parse-test@example.com>
Content-Type: text/plain; charset=UTF-8

Body text.
MAIL;

        $message = InboundMailParser::parse($raw);

        $this->assertSame(['manager@example.com', 'billing@example.com'], $message->ccEmails);
    }
}
