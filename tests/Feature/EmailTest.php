<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_email_settings(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/email')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Email')->has('inboxes', 1));
    }

    public function test_admin_can_add_second_inbox(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = User::factory()->admin()->create();
        $brandId = \App\Domains\Brands\Models\Brand::query()->first()?->id
            ?? \App\Domains\Brands\Models\Brand::query()->create(['name' => 'Default', 'slug' => 'default', 'is_default' => true])->id;

        $this->actingAs($admin)
            ->post('/settings/email/inboxes', [
                'name' => 'Billing',
                'address' => 'billing@helpdesk.test',
                'brand_id' => $brandId,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('email_inboxes', [
            'name' => 'Billing',
            'address' => 'billing@helpdesk.test',
        ]);
    }

    public function test_inbound_email_routes_by_inbox_token(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $billing = EmailInbox::query()->create([
            'name' => 'Billing',
            'address' => 'billing@helpdesk.test',
            'inbound_token' => 'billing-token',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'subject' => 'Invoice question',
            'body' => 'Need a copy of my invoice.',
            'message_id' => 'billing-001',
        ], [
            'X-Channel-Token' => 'billing-token',
        ])->assertCreated();

        $ticket = Ticket::query()->where('subject', 'Invoice question')->first();
        $this->assertSame($billing->id, $ticket->email_inbox_id);
    }

    public function test_agent_reply_queues_email_when_outbound_enabled(): void
    {
        Queue::fake();
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        MailSetting::query()->first()->update([
            'enabled' => true,
            'reply_enabled' => true,
            'driver' => 'log',
            'from_address' => 'support@helpdesk.test',
            'from_name' => 'Support',
        ]);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $agent = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00088',
            'subject' => 'Help needed',
            'contact_id' => $contact->id,
            'assigned_to' => $agent->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($agent)
            ->post("/tickets/{$ticket->id}/reply", [
                'body' => 'We are looking into this.',
                'is_internal' => false,
            ])
            ->assertRedirect();

        Queue::assertPushed(\App\Domains\Channels\Jobs\SendTicketReplyJob::class, function ($job) use ($ticket, $agent) {
            return $job->ticketId === $ticket->id && $job->agentId === $agent->id;
        });
    }

    public function test_ticket_reply_mail_renders_message_body(): void
    {
        $this->seed([TicketLookupSeeder::class, EmailSeeder::class]);

        $contact = Contact::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $agent = User::factory()->create();
        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();

        $ticket = Ticket::query()->create([
            'number' => 'HD-00088',
            'subject' => 'Help needed',
            'contact_id' => $contact->id,
            'assigned_to' => $agent->id,
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $agent->id,
            'body' => 'We are looking into this.',
            'is_internal' => false,
        ]);

        $rendered = (new \App\Domains\Channels\Mail\TicketReplyMail(
            $ticket,
            $message,
            $agent,
            'support@helpdesk.test',
            'Support',
            'ticket.1.message.1@helpdesk.test',
        ))->render();

        $this->assertStringContainsString('We are looking into this.', $rendered);
    }

    public function test_admin_can_update_outbound_settings(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/settings/email/outbound', [
                'enabled' => true,
                'reply_enabled' => true,
                'use_inbox_smtp' => false,
                'driver' => 'smtp',
                'from_address' => 'noreply@helpdesk.test',
                'from_name' => 'helpefi',
                'host' => 'smtp.mailtrap.io',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user',
                'password' => 'secret',
            ])
            ->assertRedirect();

        $setting = MailSetting::query()->first();
        $this->assertTrue($setting->enabled);
        $this->assertSame('noreply@helpdesk.test', $setting->from_address);
        $this->assertSame(MailSetting::DELIVERY_QUEUE, $setting->delivery_mode);
        $this->assertSame(MailSetting::QUEUE_REDIS, $setting->queue_connection);
    }

    public function test_outbound_mail_always_uses_redis_queue(): void
    {
        $this->seed(EmailSeeder::class);

        MailSetting::query()->first()->update([
            'queue_connection' => MailSetting::QUEUE_DATABASE,
        ]);

        app(OutboundMailService::class)->applyGlobalConfig();

        $this->assertSame(MailSetting::QUEUE_REDIS, config('queue.default'));
    }
}
