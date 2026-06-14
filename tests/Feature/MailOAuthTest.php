<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\EmailInbox;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InitializesTenancy;
use Tests\TestCase;

class MailOAuthTest extends TestCase
{
    use InitializesTenancy;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if (isset($this->tenant)) {
            $this->tenant->delete();
        }

        parent::tearDown();
    }

    public function test_central_oauth_callback_stores_google_tokens(): void
    {
        $this->provisionTenancy('mail-oauth');
        tenancy()->initialize($this->tenant);
        $this->seed(EmailSeeder::class);

        config([
            'helpdesk.mail_oauth.callback_base_url' => 'http://helpdesk.test',
            'helpdesk.mail_oauth.google.client_id' => 'google-client',
            'helpdesk.mail_oauth.google.client_secret' => 'google-secret',
        ]);

        $inbox = EmailInbox::query()->first();
        $state = 'test-state-token';
        Cache::store('central')->put('central:mail_oauth:'.$state, [
            'tenant_id' => $this->tenant->id,
            'inbox_id' => $inbox->id,
            'provider' => 'google',
        ], now()->addMinutes(10));

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access',
                'refresh_token' => 'google-refresh',
                'expires_in' => 3600,
            ]),
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'support@gmail.com']),
            'gmail.googleapis.com/gmail/v1/users/me/messages*' => Http::response(['messages' => []]),
        ]);

        $response = $this->get('http://helpdesk.test/oauth/mail/google/callback?code=abc&state='.$state);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('mail-oauth.', $location);
        $this->assertStringContainsString('/settings/email', $location);
        $this->assertStringContainsString('oauth=connected', $location);

        tenancy()->initialize($this->tenant);

        $inbox->refresh();
        $this->assertSame('oauth', $inbox->inbound_method);
        $this->assertSame('google', $inbox->oauth_provider);
        $this->assertSame('support@gmail.com', $inbox->oauth_connected_email);
        $this->assertTrue($inbox->isOAuthConnected());
    }

    public function test_redirect_uri_uses_central_callback_url_for_all_providers(): void
    {
        config(['helpdesk.mail_oauth.callback_base_url' => 'https://helpefi.com']);

        $oauth = app(\App\Domains\Channels\Services\OAuth\MailOAuthService::class);

        $this->assertSame('https://helpefi.com/oauth/mail/google/callback', $oauth->redirectUri('google'));
        $this->assertSame('https://helpefi.com/oauth/mail/microsoft/callback', $oauth->redirectUri('microsoft'));
        $this->assertSame('https://helpefi.com/oauth/mail/zoho/callback', $oauth->redirectUri('zoho'));
    }

    public function test_configured_oauth_providers_omit_unconfigured_providers(): void
    {
        config([
            'helpdesk.mail_oauth.google.client_id' => 'google-client',
            'helpdesk.mail_oauth.google.client_secret' => 'google-secret',
            'helpdesk.mail_oauth.microsoft.client_id' => null,
            'helpdesk.mail_oauth.microsoft.client_secret' => null,
            'helpdesk.mail_oauth.zoho.client_id' => 'zoho-client',
            'helpdesk.mail_oauth.zoho.client_secret' => 'zoho-secret',
        ]);

        $providers = app(\App\Domains\Channels\Services\OAuth\MailOAuthProviderFactory::class)->configuredProviders();

        $this->assertArrayHasKey('google', $providers);
        $this->assertArrayHasKey('zoho', $providers);
        $this->assertArrayNotHasKey('microsoft', $providers);
    }

    public function test_central_oauth_callback_stores_microsoft_tokens(): void
    {
        $this->provisionTenancy('mail-oauth-microsoft');
        tenancy()->initialize($this->tenant);
        $this->seed(EmailSeeder::class);

        config([
            'helpdesk.mail_oauth.callback_base_url' => 'http://helpdesk.test',
            'helpdesk.mail_oauth.microsoft.client_id' => 'microsoft-client',
            'helpdesk.mail_oauth.microsoft.client_secret' => 'microsoft-secret',
        ]);

        $inbox = EmailInbox::query()->first();
        $state = 'microsoft-state-token';
        Cache::store('central')->put('central:mail_oauth:'.$state, [
            'tenant_id' => $this->tenant->id,
            'inbox_id' => $inbox->id,
            'provider' => 'microsoft',
        ], now()->addMinutes(10));

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'microsoft-access',
                'refresh_token' => 'microsoft-refresh',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/v1.0/me*' => Http::response([
                'mail' => 'support@outlook.com',
                'userPrincipalName' => 'support@outlook.com',
            ]),
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => Http::response(['value' => []]),
        ]);

        $response = $this->get('http://helpdesk.test/oauth/mail/microsoft/callback?code=abc&state='.$state);

        $response->assertRedirect();
        $this->assertStringContainsString('oauth=connected', (string) $response->headers->get('Location'));

        tenancy()->initialize($this->tenant);

        $inbox->refresh();
        $this->assertSame('microsoft', $inbox->oauth_provider);
        $this->assertSame('support@outlook.com', $inbox->oauth_connected_email);
    }

    public function test_central_oauth_callback_stores_zoho_tokens(): void
    {
        $this->provisionTenancy('mail-oauth-zoho');
        tenancy()->initialize($this->tenant);
        $this->seed(EmailSeeder::class);

        config([
            'helpdesk.mail_oauth.callback_base_url' => 'http://helpdesk.test',
            'helpdesk.mail_oauth.zoho.client_id' => 'zoho-client',
            'helpdesk.mail_oauth.zoho.client_secret' => 'zoho-secret',
            'helpdesk.mail_oauth.zoho.region' => 'com',
        ]);

        $inbox = EmailInbox::query()->first();
        $state = 'zoho-state-token';
        Cache::store('central')->put('central:mail_oauth:'.$state, [
            'tenant_id' => $this->tenant->id,
            'inbox_id' => $inbox->id,
            'provider' => 'zoho',
        ], now()->addMinutes(10));

        Http::fake([
            'accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => 'zoho-access',
                'refresh_token' => 'zoho-refresh',
                'expires_in' => 3600,
            ]),
            'mail.zoho.com/api/accounts' => Http::response([
                'data' => [[
                    'accountId' => 'acct-1',
                    'primaryEmailAddress' => 'support@company.com',
                ]],
            ]),
            'mail.zoho.com/api/accounts/acct-1/folders' => Http::response([
                'data' => [[
                    'folderId' => 'folder-inbox',
                    'folderName' => 'Inbox',
                ]],
            ]),
            'mail.zoho.com/api/accounts/acct-1/messages/view*' => Http::response(['data' => []]),
        ]);

        $response = $this->get('http://helpdesk.test/oauth/mail/zoho/callback?code=abc&state='.$state);

        $response->assertRedirect();
        $this->assertStringContainsString('oauth=connected', (string) $response->headers->get('Location'));

        tenancy()->initialize($this->tenant);

        $inbox->refresh();
        $this->assertSame('zoho', $inbox->oauth_provider);
        $this->assertSame('support@company.com', $inbox->oauth_connected_email);
    }

    public function test_oauth_state_survives_tenant_cache_isolation_for_zoho_callback(): void
    {
        $this->provisionTenancy('mail-oauth-zoho-state');
        tenancy()->initialize($this->tenant);
        $this->seed(EmailSeeder::class);

        config([
            'helpdesk.mail_oauth.callback_base_url' => 'http://helpdesk.test',
            'helpdesk.mail_oauth.zoho.client_id' => 'zoho-client',
            'helpdesk.mail_oauth.zoho.client_secret' => 'zoho-secret',
            'helpdesk.mail_oauth.zoho.region' => 'com',
        ]);

        $inbox = EmailInbox::query()->first();
        $oauth = app(\App\Domains\Channels\Services\OAuth\MailOAuthService::class);
        $authorizationUrl = $oauth->beginConnect($inbox->id, 'zoho');
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);

        Http::fake([
            'accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => 'zoho-access',
                'refresh_token' => 'zoho-refresh',
                'expires_in' => 3600,
            ]),
            'mail.zoho.com/api/accounts' => Http::response([
                'data' => [[
                    'accountId' => 'acct-1',
                    'primaryEmailAddress' => 'support@company.com',
                ]],
            ]),
            'mail.zoho.com/api/accounts/acct-1/folders' => Http::response([
                'data' => [[
                    'folderId' => 'folder-inbox',
                    'folderName' => 'Inbox',
                ]],
            ]),
            'mail.zoho.com/api/accounts/acct-1/messages/view*' => Http::response(['data' => []]),
        ]);

        tenancy()->end();

        $response = $this->get('http://helpdesk.test/oauth/mail/zoho/callback?code=abc&state='.$query['state']);

        $response->assertRedirect();
        $this->assertStringContainsString('oauth=connected', (string) $response->headers->get('Location'));
    }

    public function test_webhook_rejected_for_oauth_inbox(): void
    {
        $this->provisionTenancy('mail-oauth-webhook');
        tenancy()->initialize($this->tenant);
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

        $domain = $this->tenant->domains()->value('domain');

        $this->postJson('http://'.$domain.'/api/v1/channels/inbound/email', [
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
        $this->provisionTenancy('mail-oauth-disconnect');
        tenancy()->initialize($this->tenant);
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

        $domain = $this->tenant->domains()->value('domain');

        $this->actingAs($admin)
            ->post("http://{$domain}/settings/email/inboxes/{$inbox->id}/oauth/disconnect")
            ->assertRedirect();

        $inbox->refresh();
        $this->assertNull($inbox->oauth_refresh_token);
        $this->assertNull($inbox->oauth_connected_email);
        $this->assertFalse($inbox->isOAuthConnected());
    }
}
