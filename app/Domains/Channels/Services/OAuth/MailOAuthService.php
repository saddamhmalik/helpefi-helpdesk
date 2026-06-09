<?php

namespace App\Domains\Channels\Services\OAuth;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Repositories\EmailInboxRepository;
use App\Support\TenantCacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MailOAuthService
{
    public function __construct(
        private EmailInboxRepository $inboxes,
        private MailOAuthProviderFactory $providers,
    ) {
    }

    public function redirectUri(string $provider): string
    {
        return url('/settings/email/oauth/'.$provider.'/callback');
    }

    public function beginConnect(int $inboxId, string $provider): string
    {
        $inbox = $this->inboxes->find($inboxId);
        $providerInstance = $this->providers->make($provider);

        if (! $providerInstance->isConfigured()) {
            throw new InvalidArgumentException(ucfirst($provider).' OAuth is not configured on the server.');
        }

        $state = Str::random(48);
        Cache::put($this->stateKey($state), [
            'inbox_id' => $inbox->id,
            'provider' => $provider,
        ], now()->addMinutes(10));

        return $providerInstance->authorizationUrl($state, $this->redirectUri($provider));
    }

    public function handleCallback(string $provider, string $code, string $state): EmailInbox
    {
        $cached = Cache::pull($this->stateKey($state));

        if (! $cached || ($cached['provider'] ?? null) !== $provider) {
            throw new InvalidArgumentException('OAuth session expired or invalid. Please try again.');
        }

        $inbox = $this->inboxes->find($cached['inbox_id']);
        $providerInstance = $this->providers->make($provider);
        $tokens = $providerInstance->exchangeCode($code, $this->redirectUri($provider));
        $accessToken = $tokens['access_token'] ?? null;

        if (! $accessToken) {
            throw new InvalidArgumentException('OAuth provider did not return an access token.');
        }

        $metadata = $providerInstance->bootstrapMetadata($accessToken);

        $this->inboxes->update($inbox, [
            'inbound_method' => 'oauth',
            'poll_enabled' => true,
            'oauth_provider' => $provider,
            'oauth_access_token' => $accessToken,
            'oauth_refresh_token' => $tokens['refresh_token'] ?? $inbox->oauth_refresh_token,
            'oauth_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'oauth_connected_email' => $providerInstance->connectedEmail($accessToken),
            'oauth_metadata' => $metadata,
            'poll_error' => null,
            'mailbox_provider' => null,
            'mailbox_protocol' => null,
            'mailbox_host' => null,
            'mailbox_port' => null,
            'mailbox_encryption' => null,
            'mailbox_username' => null,
            'mailbox_password' => null,
        ]);

        return $inbox->fresh();
    }

    public function disconnect(int $inboxId): void
    {
        $inbox = $this->inboxes->find($inboxId);

        $this->inboxes->update($inbox, [
            'oauth_provider' => null,
            'oauth_access_token' => null,
            'oauth_refresh_token' => null,
            'oauth_token_expires_at' => null,
            'oauth_connected_email' => null,
            'oauth_metadata' => null,
        ]);
    }

    public function accessToken(EmailInbox $inbox): string
    {
        if ($inbox->inbound_method !== 'oauth' || ! $inbox->oauth_provider) {
            throw new InvalidArgumentException('Inbox is not connected via OAuth.');
        }

        if ($inbox->oauth_access_token && $inbox->oauth_token_expires_at?->isFuture()) {
            return $inbox->oauth_access_token;
        }

        if (! $inbox->oauth_refresh_token) {
            throw new InvalidArgumentException('OAuth refresh token is missing. Reconnect the mailbox.');
        }

        $provider = $this->providers->make($inbox->oauth_provider);
        $tokens = $provider->refreshTokens($inbox->oauth_refresh_token);
        $accessToken = $tokens['access_token'] ?? null;

        if (! $accessToken) {
            throw new InvalidArgumentException('Unable to refresh OAuth access token.');
        }

        $inbox->update([
            'oauth_access_token' => $accessToken,
            'oauth_refresh_token' => $tokens['refresh_token'] ?? $inbox->oauth_refresh_token,
            'oauth_token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ]);

        return $accessToken;
    }

    public function providerForInbox(EmailInbox $inbox): \App\Domains\Channels\Contracts\MailOAuthProviderInterface
    {
        if (! $inbox->oauth_provider) {
            throw new InvalidArgumentException('OAuth provider is not set.');
        }

        return $this->providers->make($inbox->oauth_provider);
    }

    private function stateKey(string $state): string
    {
        return TenantCacheKey::scoped('mail_oauth:'.$state);
    }
}
