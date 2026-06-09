<?php

namespace App\Domains\Channels\Services\OAuth;

use App\Domains\Channels\Contracts\MailOAuthProviderInterface;
use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\Mailbox\InboundMailParser;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GoogleMailOAuthProvider implements MailOAuthProviderInterface
{
    public function provider(): string
    {
        return 'google';
    }

    public function isConfigured(): bool
    {
        $config = config('helpdesk.mail_oauth.google');

        return ! empty($config['client_id']) && ! empty($config['client_secret']);
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('helpdesk.mail_oauth.google.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('helpdesk.mail_oauth.google.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
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
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile');

        if (! $response->successful()) {
            return null;
        }

        return $response->json('emailAddress');
    }

    public function bootstrapMetadata(string $accessToken): array
    {
        return [];
    }

    public function fetchUnreadMessages(EmailInbox $inbox, string $accessToken): array
    {
        $list = Http::withToken($accessToken)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                'q' => 'is:unread in:inbox',
                'maxResults' => 25,
            ]);

        if (! $list->successful()) {
            throw new InvalidArgumentException($list->json('error.message') ?? 'Gmail API request failed.');
        }

        $messages = [];

        foreach ($list->json('messages', []) as $item) {
            $detail = Http::withToken($accessToken)
                ->get('https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$item['id'], [
                    'format' => 'raw',
                ]);

            if (! $detail->successful()) {
                continue;
            }

            $raw = $this->decodeRaw($detail->json('raw'));

            if ($raw === null) {
                continue;
            }

            $messages[] = InboundMailParser::parse($raw, $item['id']);
        }

        return $messages;
    }

    public function markMessageProcessed(EmailInbox $inbox, string $accessToken, InboundMailMessage $message): void
    {
        if (! $message->pollUid) {
            return;
        }

        Http::withToken($accessToken)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$message->pollUid.'/modify', [
                'removeLabelIds' => ['UNREAD'],
            ]);
    }

    private function tokenRequest(array $payload): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', array_merge($payload, [
            'client_id' => config('helpdesk.mail_oauth.google.client_id'),
            'client_secret' => config('helpdesk.mail_oauth.google.client_secret'),
        ]));

        if (! $response->successful()) {
            throw new InvalidArgumentException($response->json('error_description') ?? 'Google token request failed.');
        }

        return $response->json();
    }

    private function decodeRaw(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        return $decoded !== false ? $decoded : null;
    }
}
