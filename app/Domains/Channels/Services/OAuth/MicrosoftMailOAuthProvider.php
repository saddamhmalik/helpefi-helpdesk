<?php

namespace App\Domains\Channels\Services\OAuth;

use App\Domains\Channels\Contracts\MailOAuthProviderInterface;
use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\Mailbox\InboundMailParser;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class MicrosoftMailOAuthProvider implements MailOAuthProviderInterface
{
    public function provider(): string
    {
        return 'microsoft';
    }

    public function isConfigured(): bool
    {
        $config = config('helpdesk.mail_oauth.microsoft');

        return ! empty($config['client_id']) && ! empty($config['client_secret']);
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $tenant = config('helpdesk.mail_oauth.microsoft.tenant', 'common');

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?".http_build_query([
            'client_id' => config('helpdesk.mail_oauth.microsoft.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('helpdesk.mail_oauth.microsoft.scopes')),
            'state' => $state,
            'response_mode' => 'query',
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->tokenRequest([
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
    }

    public function refreshTokens(string $refreshToken): array
    {
        return $this->tokenRequest([
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    public function connectedEmail(string $accessToken): ?string
    {
        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me', [
                '$select' => 'mail,userPrincipalName',
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('mail') ?: $response->json('userPrincipalName');
    }

    public function bootstrapMetadata(string $accessToken): array
    {
        return [];
    }

    public function fetchUnreadMessages(EmailInbox $inbox, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages', [
                '$filter' => 'isRead eq false',
                '$top' => 25,
                '$select' => 'id',
            ]);

        if (! $response->successful()) {
            throw new InvalidArgumentException($response->json('error.message') ?? 'Microsoft Graph request failed.');
        }

        $messages = [];

        foreach ($response->json('value', []) as $item) {
            $mime = Http::withToken($accessToken)
                ->get('https://graph.microsoft.com/v1.0/me/messages/'.$item['id'].'/$value');

            if (! $mime->successful()) {
                continue;
            }

            $messages[] = InboundMailParser::parse($mime->body(), $item['id']);
        }

        return $messages;
    }

    public function markMessageProcessed(EmailInbox $inbox, string $accessToken, InboundMailMessage $message): void
    {
        if (! $message->pollUid) {
            return;
        }

        Http::withToken($accessToken)
            ->patch('https://graph.microsoft.com/v1.0/me/messages/'.$message->pollUid, [
                'isRead' => true,
            ]);
    }

    private function tokenRequest(array $payload): array
    {
        $tenant = config('helpdesk.mail_oauth.microsoft.tenant', 'common');

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", array_merge($payload, [
            'client_id' => config('helpdesk.mail_oauth.microsoft.client_id'),
            'client_secret' => config('helpdesk.mail_oauth.microsoft.client_secret'),
        ]));

        if (! $response->successful()) {
            throw new InvalidArgumentException($response->json('error_description') ?? 'Microsoft token request failed.');
        }

        return $response->json();
    }
}
