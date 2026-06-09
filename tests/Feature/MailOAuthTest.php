<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\OAuth\MailOAuthService;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_callback_stores_google_tokens(): void
    {
        $this->seed(EmailSeeder::class);
        config([
            'helpdesk.mail_oauth.google.client_id' => 'google-client',
            'helpdesk.mail_oauth.google.client_secret' => 'google-secret',
        ]);

        $inbox = EmailInbox::query()->first();
        $state = 'test-state-token';
        Cache::put('mail_oauth:'.$state, ['inbox_id' => $inbox->id, 'provider' => 'google'], now()->addMinutes(10));

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access',
                'refresh_token' => 'google-refresh',
                'expires_in' => 3600,
            ]),
            'gmail.googleapis.com/*' => Http::response(['emailAddress' => 'support@gmail.com']),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/email/oauth/google/callback?code=abc&state='.$state)
            ->assertRedirect(route('settings.email'));

        $inbox->refresh();
        $this->assertSame('oauth', $inbox->inbound_method);
        $this->assertSame('google', $inbox->oauth_provider);
        $this->assertSame('support@gmail.com', $inbox->oauth_connected_email);
        $this->assertTrue($inbox->isOAuthConnected());
    }

    public function test_webhook_rejected_for_oauth_inbox(): void
    {
        $this->seed([\Database\Seeders\TicketLookupSeeder::class, \Database\Seeders\ChannelSeeder::class, EmailSeeder::class]);

        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'oauth',
            'poll_enabled' => true,
            'oauth_provider' => 'google',
            'oauth_refresh_token' => 'refresh',
            'oauth_access_token' => 'access',
            'oauth_token_expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/v1/channels/inbound/email', [
            'from_email' => 'customer@example.com',
            'subject' => 'Should fail',
            'body' => 'Webhook on oauth inbox',
            'message_id' => 'webhook-on-oauth',
        ], [
            'X-Channel-Token' => $inbox->inbound_token,
        ])->assertStatus(422);
    }

    public function test_admin_can_disconnect_oauth(): void
    {
        $this->seed(EmailSeeder::class);
        $admin = User::factory()->admin()->create();
        $inbox = EmailInbox::query()->first();
        $inbox->update([
            'inbound_method' => 'oauth',
            'oauth_provider' => 'google',
            'oauth_refresh_token' => 'refresh',
            'oauth_access_token' => 'access',
            'oauth_connected_email' => 'support@gmail.com',
        ]);

        $this->actingAs($admin)
            ->post("/settings/email/inboxes/{$inbox->id}/oauth/disconnect")
            ->assertRedirect();

        $inbox->refresh();
        $this->assertNull($inbox->oauth_refresh_token);
        $this->assertNull($inbox->oauth_connected_email);
        $this->assertFalse($inbox->isOAuthConnected());
    }
}
