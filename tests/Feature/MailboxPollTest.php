<?php

namespace Tests\Feature;

use App\Domains\Channels\Contracts\MailboxReaderInterface;
use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\Mailbox\EmailQuoteStripper;
use App\Domains\Channels\Services\Mailbox\InboundMailParser;
use App\Domains\Channels\Services\Mailbox\MailboxReaderFactory;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\EmailSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MailboxPollTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_parser_extracts_headers_and_body(): void
    {
        $raw = <<<'MAIL'
From: Jane Customer <jane@example.com>
To: support@helpdesk.test
Subject: Password reset
Message-ID: <test-message-001@example.com>
Content-Type: text/plain; charset=UTF-8

I cannot reset my password.
MAIL;

        $message = InboundMailParser::parse($raw, 'fallback-id');

        $this->assertSame('test-message-001@example.com', $message->messageId);
        $this->assertSame('jane@example.com', $message->fromEmail);
        $this->assertSame('Jane Customer', $message->fromName);
        $this->assertSame('Password reset', $message->subject);
        $this->assertStringContainsString('cannot reset', $message->body);
    }

    public function test_mail_parser_uses_reply_to_for_sender(): void
    {
        $raw = <<<'MAIL'
From: Support Bot <bot@helpdesk.test>
Reply-To: Jane Customer <jane@example.com>
Subject: Help
Message-ID: <reply-to-test@example.com>
Content-Type: text/plain; charset=UTF-8

Need help please.
MAIL;

        $message = InboundMailParser::parse($raw);

        $this->assertSame('jane@example.com', $message->fromEmail);
        $this->assertSame('Jane Customer', $message->fromName);
    }

    public function test_mail_parser_prefers_html_when_plain_is_low_quality(): void
    {
        $boundary = 'boundary123';
        $raw = <<<MAIL
From: Google <no-reply@accounts.google.com>
Subject: Security alert
Message-ID: <google-test@example.com>
Content-Type: multipart/alternative; boundary="{$boundary}"

--{$boundary}
Content-Type: text/plain; charset="UTF-8"; format=flowed; delsp=yes

[image: Google]
App password created

--{$boundary}
Content-Type: text/html; charset="UTF-8"

<html><body><p>App password created to sign in to your account.</p><p>kaamil.hk2025@gmail.com</p></body></html>
--{$boundary}--
MAIL;

        $message = InboundMailParser::parse($raw);

        $this->assertStringContainsString('App password created to sign in', $message->body);
        $this->assertStringNotContainsString('[image: Google]', $message->body);
    }

    public function test_mail_parser_unwraps_flowed_plain_text(): void
    {
        $raw = <<<'MAIL'
From: Jane Customer <jane@example.com>
Subject: Flowed
Message-ID: <flowed-test@example.com>
Content-Type: text/plain; charset=UTF-8; format=flowed; delsp=yes

This is a long sentence that was 
wrapped using flowed format.
MAIL;

        $message = InboundMailParser::parse($raw);

        $this->assertSame('This is a long sentence that was wrapped using flowed format.', $message->body);
    }

    public function test_mail_parser_extracts_attachments_from_multipart(): void
    {
        $boundary = 'mixed-boundary';
        $fileContent = 'Hello attachment content';
        $encoded = base64_encode($fileContent);
        $raw = <<<MAIL
From: Jane Customer <jane@example.com>
To: support@helpdesk.test
Subject: File attached
Message-ID: <attachment-test@example.com>
Content-Type: multipart/mixed; boundary="{$boundary}"

--{$boundary}
Content-Type: text/plain; charset=UTF-8

Please see the attached file.

--{$boundary}
Content-Type: application/pdf; name="invoice.pdf"
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="invoice.pdf"

{$encoded}
--{$boundary}--
MAIL;

        $message = InboundMailParser::parse($raw);

        $this->assertStringContainsString('attached file', $message->body);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('invoice.pdf', $message->attachments[0]->filename);
        $this->assertSame($fileContent, $message->attachments[0]->content);
        $this->assertSame('application/pdf', $message->attachments[0]->mimeType);
    }

    public function test_inbound_email_stores_message_attachments(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();
        $fileContent = 'test file bytes';

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'subject' => 'Support with attachment',
            'body' => 'Please review the attached document.',
            'message_id' => 'attachment-inbound-001',
            'attachments' => [
                [
                    'filename' => 'report.txt',
                    'content' => base64_encode($fileContent),
                    'mime_type' => 'text/plain',
                ],
            ],
        ], [
            'X-Channel-Token' => $inbox->inbound_token,
        ])->assertSuccessful();

        $this->assertDatabaseHas('ticket_attachments', [
            'filename' => 'report.txt',
        ]);

        $attachment = \App\Domains\Tickets\Models\TicketAttachment::query()
            ->where('filename', 'report.txt')
            ->first();

        $this->assertNotNull($attachment->ticket_message_id);
        $this->assertSame($fileContent, \Illuminate\Support\Facades\Storage::disk('public')->get($attachment->path));
    }

    public function test_mail_parser_ignores_inline_images(): void
    {
        $boundary = 'related-boundary';
        $raw = <<<MAIL
From: Jane Customer <jane@example.com>
Subject: Screenshot issue
Message-ID: <inline-image-test@example.com>
Content-Type: multipart/related; boundary="{$boundary}"

--{$boundary}
Content-Type: text/html; charset=UTF-8

<html><body>See inline image</body></html>

--{$boundary}
Content-Type: image/png; name="screenshot.png"
Content-Transfer-Encoding: base64
Content-Disposition: inline; filename="screenshot.png"

aGVsbG8=
--{$boundary}--
MAIL;

        $message = InboundMailParser::parse($raw);

        $this->assertCount(0, $message->attachments);
    }

    public function test_inbound_email_reply_does_not_duplicate_existing_attachment(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();
        $fileContent = 'duplicate-safe-bytes';

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'subject' => 'Support with attachment',
            'body' => 'Please review the attached document.',
            'message_id' => 'attachment-original-001',
            'attachments' => [
                [
                    'filename' => 'report.txt',
                    'content' => base64_encode($fileContent),
                    'mime_type' => 'text/plain',
                ],
            ],
        ], [
            'X-Channel-Token' => $inbox->inbound_token,
        ])->assertSuccessful();

        $ticket = \App\Domains\Tickets\Models\Ticket::query()->where('subject', 'Support with attachment')->first();

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'subject' => 'Re: Support with attachment',
            'body' => 'Following up on the attachment.',
            'message_id' => 'attachment-reply-001',
            'in_reply_to' => ['attachment-original-001'],
            'attachments' => [
                [
                    'filename' => 'report.txt',
                    'content' => base64_encode($fileContent),
                    'mime_type' => 'text/plain',
                ],
            ],
        ], [
            'X-Channel-Token' => $inbox->inbound_token,
        ])->assertSuccessful()->assertJsonPath('action', 'reply');

        $this->assertSame(1, \App\Domains\Tickets\Models\TicketAttachment::query()
            ->where('ticket_id', $ticket->id)
            ->where('filename', 'report.txt')
            ->count());
    }

    public function test_mail_parser_extracts_in_reply_to_for_threading(): void
    {
        $raw = <<<'MAIL'
From: Jane Customer <jane@example.com>
Subject: Re: Login issue
Message-ID: <reply-msg@example.com>
In-Reply-To: <original-msg@example.com>
References: <original-msg@example.com> <another-msg@example.com>
Content-Type: text/plain; charset=UTF-8

Follow up message.
MAIL;

        $message = InboundMailParser::parse($raw);

        $this->assertSame(['original-msg@example.com'], $message->inReplyTo);
        $this->assertSame(['original-msg@example.com', 'another-msg@example.com'], $message->references);
    }

    public function test_inbound_email_strips_gmail_quoted_reply(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'subject' => 'Re: [HD-00006] Test ticket',
            'body' => <<<'BODY'
Hi! How are you?

On Sun, 7 Jun 2026 at 6:39 PM, Saddam Hussain Malik <saddahmhalik@gmail.com> wrote:

> This is a test email to check the ticket functionality
BODY,
            'message_id' => 'gmail-reply-001',
            'in_reply_to' => ['ticket.6.message.1@helpdesk.test'],
        ], [
            'X-Channel-Token' => $inbox->inbound_token,
        ])->assertSuccessful();

        $this->assertDatabaseHas('ticket_messages', [
            'body' => 'Hi! How are you?',
        ]);
    }

    public function test_quote_stripper_removes_outlook_original_message_block(): void
    {
        $stripper = app(EmailQuoteStripper::class);

        $body = <<<'BODY'
Thanks for the update.

-----Original Message-----
From: Support
Sent: Monday
Subject: Test

Old message body
BODY;

        $this->assertSame('Thanks for the update.', $stripper->strip($body));
    }

    public function test_quote_stripper_removes_gmail_multiline_attribution(): void
    {
        $stripper = app(EmailQuoteStripper::class);

        $body = <<<'BODY'
On Sun, 7 Jun 2026 at 9:36 PM, Saddam Hussain Malik <saddamhmalik@gmail.com>
wrote:
> Quoted reply text
BODY;

        $this->assertSame('', $stripper->strip($body));

        $bodyWithReply = <<<'BODY'
Here is my update.

On Sun, 7 Jun 2026 at 9:36 PM, Saddam Hussain Malik <saddamhmalik@gmail.com>
wrote:
> Quoted reply text
BODY;

        $this->assertSame('Here is my update.', $stripper->strip($bodyWithReply));
    }

    public function test_poll_command_imports_fetched_messages(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'poll',
            'poll_enabled' => true,
            'mailbox_provider' => 'gmail',
            'mailbox_protocol' => 'imap',
            'mailbox_host' => 'imap.gmail.com',
            'mailbox_port' => 993,
            'mailbox_encryption' => 'ssl',
            'mailbox_username' => 'support@helpdesk.test',
            'mailbox_password' => 'secret',
        ]);

        $reader = Mockery::mock(MailboxReaderInterface::class);
        $reader->shouldReceive('fetch')->once()->andReturn([
            new InboundMailMessage(
                messageId: 'poll-msg-1',
                fromEmail: 'customer@example.com',
                fromName: 'Customer',
                subject: 'Polled support request',
                body: 'Help from mailbox polling.',
                pollUid: 'poll-msg-1',
            ),
        ]);
        $reader->shouldReceive('markMessageProcessed')->once();

        $this->mock(MailboxReaderFactory::class, function ($mock) use ($reader) {
            $mock->shouldReceive('forInbox')->andReturn($reader);
        });

        $this->artisan('channels:poll-inboxes')->assertSuccessful();

        $this->assertDatabaseHas('tickets', ['subject' => 'Polled support request']);
        $ticket = Ticket::query()->where('subject', 'Polled support request')->first();
        $this->assertSame($inbox->id, $ticket->email_inbox_id);
    }

    public function test_poll_command_logs_mailbox_errors_without_failing_scheduler(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'poll',
            'poll_enabled' => true,
            'mailbox_provider' => 'gmail',
            'mailbox_protocol' => 'imap',
            'mailbox_host' => 'imap.gmail.com',
            'mailbox_port' => 993,
            'mailbox_encryption' => 'ssl',
            'mailbox_username' => 'support@helpdesk.test',
            'mailbox_password' => 'secret',
        ]);

        $reader = Mockery::mock(MailboxReaderInterface::class);
        $reader->shouldReceive('fetch')->once()->andThrow(new \InvalidArgumentException('IMAP connection failed: timeout'));

        $this->mock(MailboxReaderFactory::class, function ($mock) use ($reader) {
            $mock->shouldReceive('forInbox')->andReturn($reader);
        });

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with('Mailbox poll failed.', Mockery::on(function (array $context) use ($inbox) {
                return ($context['inbox'] === $inbox->name || $context['inbox'] === $inbox->address)
                    && str_contains($context['message'], 'IMAP connection failed');
            }));

        $this->artisan('channels:poll-inboxes')->assertSuccessful();

        $inbox->refresh();
        $this->assertSame('IMAP connection failed: timeout', $inbox->poll_error);
    }

    public function test_admin_can_save_mailbox_polling_settings(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = User::factory()->admin()->create();
        $inbox = EmailInbox::query()->first();

        $this->actingAs($admin)
            ->put("/settings/email/inboxes/{$inbox->id}", [
                'name' => $inbox->name,
                'address' => $inbox->address,
                'is_active' => true,
                'inbound_method' => 'poll',
                'mailbox_provider' => 'gmail',
                'mailbox_protocol' => 'imap',
                'mailbox_host' => 'imap.gmail.com',
                'mailbox_port' => 993,
                'mailbox_encryption' => 'ssl',
                'mailbox_username' => 'support@helpdesk.test',
                'mailbox_password' => 'app-password',
                'mailbox_folder' => 'INBOX',
            ])
            ->assertRedirect();

        $inbox->refresh();
        $this->assertSame('poll', $inbox->inbound_method);
        $this->assertTrue($inbox->poll_enabled);
        $this->assertSame('imap', $inbox->mailbox_protocol);
        $this->assertSame('imap.gmail.com', $inbox->mailbox_host);
    }

    public function test_oauth_poll_command_imports_fetched_messages(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'oauth',
            'poll_enabled' => true,
            'oauth_provider' => 'google',
            'oauth_refresh_token' => 'refresh',
            'oauth_access_token' => 'access',
            'oauth_token_expires_at' => now()->addHour(),
            'oauth_connected_email' => 'support@helpdesk.test',
        ]);

        $reader = Mockery::mock(MailboxReaderInterface::class);
        $reader->shouldReceive('fetch')->once()->andReturn([
            new InboundMailMessage(
                messageId: 'oauth-msg-1',
                fromEmail: 'customer@example.com',
                fromName: 'Customer',
                subject: 'OAuth support request',
                body: 'Help from OAuth polling.',
                pollUid: 'oauth-msg-1',
            ),
        ]);
        $reader->shouldReceive('markMessageProcessed')->once();

        $this->mock(MailboxReaderFactory::class, function ($mock) use ($reader) {
            $mock->shouldReceive('forInbox')->andReturn($reader);
        });

        $this->artisan('channels:poll-inboxes')->assertSuccessful();

        $this->assertDatabaseHas('tickets', ['subject' => 'OAuth support request']);
    }

    public function test_saving_poll_settings_does_not_clear_oauth_connection(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = User::factory()->admin()->create();
        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'oauth',
            'poll_enabled' => true,
            'oauth_provider' => 'google',
            'oauth_refresh_token' => 'refresh-token',
            'oauth_access_token' => 'access-token',
            'oauth_token_expires_at' => now()->addHour(),
            'oauth_connected_email' => 'support@helpdesk.test',
        ]);

        $this->actingAs($admin)
            ->put("/settings/email/inboxes/{$inbox->id}", [
                'name' => $inbox->name,
                'address' => $inbox->address,
                'is_active' => true,
                'inbound_method' => 'poll',
                'mailbox_provider' => 'gmail',
                'mailbox_protocol' => 'imap',
                'mailbox_host' => 'imap.gmail.com',
                'mailbox_port' => 993,
                'mailbox_encryption' => 'ssl',
                'mailbox_username' => 'support@helpdesk.test',
                'mailbox_password' => 'app-password',
                'mailbox_folder' => 'INBOX',
            ])
            ->assertRedirect();

        $inbox->refresh();
        $this->assertSame('oauth', $inbox->inbound_method);
        $this->assertSame('google', $inbox->oauth_provider);
        $this->assertSame('refresh-token', $inbox->oauth_refresh_token);
    }

    public function test_webhook_rejected_for_poll_inbox(): void
    {
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();
        $inbox->update(['inbound_method' => 'poll', 'poll_enabled' => true]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'subject' => 'Should fail',
            'body' => 'Webhook on poll inbox',
            'message_id' => 'webhook-on-poll',
        ], [
            'X-Channel-Token' => $inbox->inbound_token,
        ])->assertStatus(422);
    }

    public function test_imap_port_993_uses_implicit_ssl_when_encryption_is_tls(): void
    {
        $config = new \App\Domains\Channels\Services\Mailbox\MailboxConnectionConfig(
            protocol: 'imap',
            host: 'imap.gmail.com',
            port: 993,
            encryption: 'tls',
            username: 'test@gmail.com',
            password: 'secret',
            folder: 'INBOX',
        );

        $this->assertSame('ssl://imap.gmail.com:993', $config->socketAddress());
        $this->assertFalse($config->usesStartTls());
    }
}
