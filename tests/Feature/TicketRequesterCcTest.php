<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketCc;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Domains\Tickets\Services\TicketCcService;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketRequesterCcTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function seedTicketMeta(): array
    {
        $status = TicketStatus::query()->create(['name' => 'Open', 'slug' => 'open', 'color' => '#000', 'sort_order' => 1, 'is_closed' => false]);
        $priority = TicketPriority::query()->create(['name' => 'Normal', 'slug' => 'normal', 'sort_order' => 1]);

        return [$status, $priority];
    }

    public function test_create_ticket_with_new_requester_email_creates_contact(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tickets', [
                'subject' => 'Billing question',
                'requester_email' => 'new.customer@example.com',
                'requester_name' => 'New Customer',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'email' => 'new.customer@example.com',
            'name' => 'New Customer',
        ]);

        $contact = Contact::query()->where('email', 'new.customer@example.com')->first();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Billing question',
            'contact_id' => $contact->id,
        ]);
    }

    public function test_create_ticket_stores_cc_emails(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();
        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $this->actingAs($user)
            ->post('/tickets', [
                'subject' => 'Shared issue',
                'contact_id' => $contact->id,
                'cc_emails' => ['manager@example.com', 'billing@example.com'],
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Shared issue')->first();

        $this->assertDatabaseHas('ticket_ccs', [
            'ticket_id' => $ticket->id,
            'email' => 'manager@example.com',
        ]);
        $this->assertDatabaseHas('ticket_ccs', [
            'ticket_id' => $ticket->id,
            'email' => 'billing@example.com',
        ]);
    }

    public function test_cc_emails_excluded_when_matching_requester(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();
        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $this->actingAs($user)
            ->post('/tickets', [
                'subject' => 'Duplicate CC',
                'contact_id' => $contact->id,
                'cc_emails' => ['jane@example.com', 'other@example.com'],
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Duplicate CC')->first();

        $this->assertDatabaseMissing('ticket_ccs', [
            'ticket_id' => $ticket->id,
            'email' => 'jane@example.com',
        ]);
        $this->assertDatabaseHas('ticket_ccs', [
            'ticket_id' => $ticket->id,
            'email' => 'other@example.com',
        ]);
    }

    public function test_contact_search_returns_matching_customers(): void
    {
        $user = User::factory()->create();
        Contact::query()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);

        $this->actingAs($user)
            ->getJson('/contacts/search?q=alice')
            ->assertOk()
            ->assertJsonPath('results.0.email', 'alice@example.com');
    }

    public function test_recipients_for_ticket_excludes_requester_email(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $ticket = Ticket::query()->create([
            'number' => 'HD-00100',
            'subject' => 'Help needed',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketCc::query()->create(['ticket_id' => $ticket->id, 'email' => 'jane@example.com']);
        TicketCc::query()->create(['ticket_id' => $ticket->id, 'email' => 'cc1@example.com']);

        $recipients = app(TicketCcService::class)->recipientsForTicket($ticket->fresh(['contact', 'ccs']));

        $this->assertSame(['cc1@example.com'], $recipients);
    }

    public function test_ticket_reply_sends_outbound_mail_when_enabled(): void
    {
        Mail::fake();
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        MailSetting::query()->first()->update([
            'enabled' => true,
            'reply_enabled' => true,
            'driver' => 'log',
            'from_address' => 'support@helpdesk.test',
            'from_name' => 'Support',
        ]);

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $agent = User::factory()->create();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00100',
            'subject' => 'Help needed',
            'contact_id' => $contact->id,
            'assigned_to' => $agent->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketCc::query()->create([
            'ticket_id' => $ticket->id,
            'email' => 'cc1@example.com',
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $agent->id,
            'body' => 'We are looking into this.',
            'is_internal' => false,
        ]);

        app(OutboundMailService::class)->applyGlobalConfig();
        app(OutboundMailService::class)->deliverTicketReply(
            $ticket->fresh(['contact', 'ccs']),
            $message,
            $agent,
        );

        Mail::assertSent(\App\Domains\Channels\Mail\TicketReplyMail::class);
    }

    public function test_update_ticket_cannot_remove_requester(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();
        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $ticket = Ticket::query()->create([
            'number' => 'HD-00101',
            'subject' => 'Keep requester',
            'contact_id' => $contact->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($user)
            ->put("/tickets/{$ticket->id}", [
                'contact_id' => '',
                '_autosave' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_merge_copies_cc_emails_to_target_ticket(): void
    {
        [$status, $priority] = $this->seedTicketMeta();
        $user = User::factory()->create();

        $target = Ticket::query()->create([
            'number' => 'HD-00001',
            'subject' => 'Target',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $source = Ticket::query()->create([
            'number' => 'HD-00002',
            'subject' => 'Source',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        TicketCc::query()->create(['ticket_id' => $target->id, 'email' => 'existing@example.com']);
        TicketCc::query()->create(['ticket_id' => $source->id, 'email' => 'source@example.com']);

        $this->actingAs($user)
            ->post("/tickets/{$target->id}/merge", ['source_ticket_id' => $source->id])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_ccs', [
            'ticket_id' => $target->id,
            'email' => 'existing@example.com',
        ]);
        $this->assertDatabaseHas('ticket_ccs', [
            'ticket_id' => $target->id,
            'email' => 'source@example.com',
        ]);
    }
}
