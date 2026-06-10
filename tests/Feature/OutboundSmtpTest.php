<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Channels\Services\OutboundSmtpResolver;
use Database\Seeders\EmailSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OutboundSmtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_smtp_resolver_uses_gmail_preset_for_gmail_inbox(): void
    {
        $this->seed(EmailSeeder::class);

        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'poll',
            'mailbox_provider' => 'gmail',
            'mailbox_username' => 'support@gmail.com',
            'mailbox_password' => 'app-password',
        ]);

        $snapshot = app(OutboundSmtpResolver::class)->inboxSnapshot($inbox->fresh());

        $this->assertSame('smtp.gmail.com', $snapshot['host']);
        $this->assertSame(587, $snapshot['port']);
        $this->assertTrue($snapshot['can_use_same_credentials']);
    }

    public function test_outbound_save_requires_smtp_fields_when_not_using_inbox(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = \App\Models\User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/settings/email/outbound', [
                'enabled' => true,
                'reply_enabled' => true,
                'use_inbox_smtp' => false,
                'driver' => 'smtp',
                'from_address' => '',
                'from_name' => 'Support',
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
            ])
            ->assertSessionHasErrors('outbound');
    }

    public function test_outbound_can_save_using_inbox_smtp(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = \App\Models\User::factory()->admin()->create();
        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'poll',
            'mailbox_provider' => 'gmail',
            'mailbox_username' => $inbox->address,
            'mailbox_password' => 'app-password',
        ]);

        $this->actingAs($admin)
            ->put('/settings/email/outbound', [
                'enabled' => true,
                'reply_enabled' => true,
                'use_inbox_smtp' => true,
                'email_inbox_id' => $inbox->id,
                'driver' => 'smtp',
            ])
            ->assertRedirect();

        $setting = MailSetting::query()->first();
        $this->assertTrue($setting->use_inbox_smtp);
        $this->assertSame($inbox->id, $setting->email_inbox_id);
    }

    public function test_resolver_throws_when_oauth_inbox_has_no_smtp_password(): void
    {
        $this->seed(EmailSeeder::class);

        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'oauth',
            'oauth_provider' => 'google',
            'oauth_refresh_token' => 'token',
        ]);

        $setting = MailSetting::query()->first();
        $setting->use_inbox_smtp = true;
        $setting->setRelation('emailInbox', $inbox);

        $this->expectException(InvalidArgumentException::class);

        app(OutboundSmtpResolver::class)->resolveFromInbox($setting);
    }

    public function test_normalize_host_maps_gmail_domain_to_smtp_host(): void
    {
        $resolver = app(OutboundSmtpResolver::class);

        $this->assertSame('smtp.gmail.com', $resolver->normalizeHost('gmail.com'));
        $this->assertSame('smtp.gmail.com', $resolver->normalizeHost('gmail.com', 'user@gmail.com'));

        $config = $resolver->resolve(MailSetting::make([
            'driver' => 'smtp',
            'host' => 'gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@gmail.com',
            'password' => 'secret',
            'from_address' => 'user@gmail.com',
            'from_name' => 'Support',
        ]));

        $this->assertSame('smtp.gmail.com', $config['host']);
    }

    public function test_outbound_save_normalizes_gmail_host(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = \App\Models\User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/settings/email/outbound', [
                'enabled' => true,
                'reply_enabled' => true,
                'use_inbox_smtp' => false,
                'driver' => 'smtp',
                'from_address' => 'user@gmail.com',
                'from_name' => 'Support',
                'host' => 'gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user@gmail.com',
                'password' => 'secret',
            ])
            ->assertRedirect();

        $this->assertSame('smtp.gmail.com', MailSetting::query()->first()->host);
    }

    public function test_normalize_password_strips_spaces(): void
    {
        $resolver = app(OutboundSmtpResolver::class);

        $this->assertSame('abcdefghijklmnop', $resolver->normalizePassword('abcd efgh ijkl mnop'));
    }

    public function test_manual_smtp_reuses_inbox_app_password_for_matching_address(): void
    {
        $this->seed(EmailSeeder::class);

        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'address' => 'support@gmail.com',
            'inbound_method' => 'poll',
            'mailbox_provider' => 'gmail',
            'mailbox_username' => 'support@gmail.com',
            'mailbox_password' => 'correct-app-password',
        ]);

        $setting = MailSetting::query()->first();
        $setting->update([
            'use_inbox_smtp' => false,
            'driver' => 'smtp',
            'from_address' => 'support@gmail.com',
            'username' => 'support@gmail.com',
            'host' => 'smtp.gmail.com',
            'password' => 'wrong-outbound-password',
        ]);

        $config = app(OutboundSmtpResolver::class)->resolve($setting->fresh());

        $this->assertSame('correct-app-password', $config['password']);
    }
}
