<?php

namespace App\Domains\Channels\Services\OAuth;

use App\Domains\Channels\Contracts\MailOAuthProviderInterface;
use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\Mailbox\InboundMailParser;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ZohoMailOAuthProvider implements MailOAuthProviderInterface
{
    public function provider(): string
    {
        return 'zoho';
    }

    public function isConfigured(): bool
    {
        $config = config('helpdesk.mail_oauth.zoho');

        return ! empty($config['client_id']) && ! empty($config['client_secret']);
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->accountsBase().'/oauth/v2/auth?'.http_build_query([
            'client_id' => config('helpdesk.mail_oauth.zoho.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', config('helpdesk.mail_oauth.zoho.scopes')),
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
        $metadata = $this->resolveAccountMetadata($accessToken);

        return $metadata['email'] ?? null;
    }

    public function bootstrapMetadata(string $accessToken): array
    {
        return $this->resolveAccountMetadata($accessToken);
    }

    public function fetchUnreadMessages(EmailInbox $inbox, string $accessToken): array
    {
        $metadata = $inbox->oauth_metadata ?? [];
        $accountId = $metadata['zoho_account_id'] ?? null;
        $folderId = $metadata['zoho_folder_id'] ?? null;

        if (! $accountId || ! $folderId) {
            $metadata = $this->resolveAccountMetadata($accessToken);
            $accountId = $metadata['zoho_account_id'] ?? null;
            $folderId = $metadata['zoho_folder_id'] ?? null;
        }

        if (! $accountId || ! $folderId) {
            throw new InvalidArgumentException('Zoho mailbox metadata is missing.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
        ])->get($this->mailBase()."/api/accounts/{$accountId}/messages/view", [
            'status' => 'unread',
            'folderId' => $folderId,
            'limit' => 25,
        ]);

        if (! $response->successful()) {
            throw new InvalidArgumentException($response->json('message') ?? 'Zoho Mail API request failed.');
        }

        $messages = [];

        foreach ($response->json('data', []) as $item) {
            $messageId = $item['messageId'] ?? null;

            if (! $messageId) {
                continue;
            }

            $content = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken '.$accessToken,
            ])->get($this->mailBase()."/api/accounts/{$accountId}/folders/{$folderId}/messages/{$messageId}/content");

            if (! $content->successful()) {
                continue;
            }

            $rawContent = $content->json('data.content');

            if (is_string($rawContent) && trim($rawContent) !== '' && $this->isRfc822Message($rawContent)) {
                $raw = $rawContent;
            } else {
                $raw = $this->buildSyntheticRaw($item, is_string($rawContent) ? $rawContent : null);
            }

            $messages[] = InboundMailParser::parse($raw, (string) $messageId);
        }

        return $messages;
    }

    public function markMessageProcessed(EmailInbox $inbox, string $accessToken, InboundMailMessage $message): void
    {
        if (! $message->pollUid) {
            return;
        }

        $metadata = $inbox->oauth_metadata ?? [];
        $accountId = $metadata['zoho_account_id'] ?? null;
        $folderId = $metadata['zoho_folder_id'] ?? null;

        if (! $accountId || ! $folderId) {
            return;
        }

        Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
        ])->put($this->mailBase()."/api/accounts/{$accountId}/updatemessage", [
            'mode' => 'markAsRead',
            'messageId' => [$message->pollUid],
            'folderId' => $folderId,
        ]);
    }

    private function resolveAccountMetadata(string $accessToken): array
    {
        $accounts = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
        ])->get($this->mailBase().'/api/accounts');

        if (! $accounts->successful()) {
            throw new InvalidArgumentException($accounts->json('message') ?? 'Unable to load Zoho mail accounts.');
        }

        $account = $accounts->json('data.0');

        if (! $account) {
            throw new InvalidArgumentException('No Zoho mail account found.');
        }

        $accountId = $account['accountId'] ?? null;
        $email = $account['primaryEmailAddress'] ?? $account['emailAddress'][0]['mailId'] ?? null;
        $folderId = null;

        if ($accountId) {
            $folders = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken '.$accessToken,
            ])->get($this->mailBase()."/api/accounts/{$accountId}/folders");

            if ($folders->successful()) {
                foreach ($folders->json('data', []) as $folder) {
                    if (($folder['folderName'] ?? '') === 'Inbox' || ($folder['folderType'] ?? '') === 'Inbox') {
                        $folderId = $folder['folderId'] ?? null;
                        break;
                    }
                }

                $folderId ??= $folders->json('data.0.folderId');
            }
        }

        return [
            'email' => $email,
            'zoho_account_id' => $accountId,
            'zoho_folder_id' => $folderId,
            'zoho_region' => config('helpdesk.mail_oauth.zoho.region', 'com'),
        ];
    }

    private function buildSyntheticRaw(array $item, ?string $body = null): string
    {
        $from = $this->resolveFromHeader($item);
        $subject = trim((string) ($item['subject'] ?? ''));
        $subject = $subject !== '' ? $subject : 'Email from Zoho';
        $messageId = $item['messageId'] ?? uniqid();
        $bodyText = $this->resolveBodyText($item, $body);

        return "From: {$from}\r\nSubject: {$subject}\r\nMessage-ID: <zoho-{$messageId}@zoho>\r\n\r\n{$bodyText}";
    }

    private function resolveFromHeader(array $item): string
    {
        $email = trim((string) ($item['fromAddress'] ?? ''));

        if ($email === '' && isset($item['sender']) && str_contains((string) $item['sender'], '@')) {
            $email = trim((string) $item['sender']);
        }

        if ($email === '') {
            $parsed = $this->parseZohoAddress($item['from'] ?? null);

            if ($parsed !== '') {
                return $parsed;
            }
        }

        $name = trim((string) ($item['sender'] ?? ''));

        if ($name !== '' && ! str_contains($name, '@') && $email !== '') {
            return "{$name} <{$email}>";
        }

        if ($email !== '') {
            return $email;
        }

        return 'unknown@zoho.test';
    }

    private function parseZohoAddress(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        if (preg_match('/<([^>]+@[^>]+)>/', $value, $matches)) {
            $email = strtolower(trim($matches[1]));
            $name = trim(str_replace($matches[0], '', $value), ' "');

            if ($name !== '') {
                return "{$name} <{$email}>";
            }

            return $email;
        }

        if (filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($value));
        }

        return '';
    }

    private function resolveBodyText(array $item, ?string $body): string
    {
        if (is_string($body) && trim($body) !== '') {
            if ($this->looksLikeHtml($body)) {
                return $this->htmlToPlainText($body);
            }

            return trim($body);
        }

        $summary = trim((string) ($item['summary'] ?? $item['snippet'] ?? ''));

        return $summary !== '' ? $summary : '(no body)';
    }

    private function isRfc822Message(string $raw): bool
    {
        $headerBlock = explode("\n\n", str_replace("\r\n", "\n", $raw), 2)[0];

        return (bool) preg_match('/^from\s*:/mi', $headerBlock);
    }

    private function looksLikeHtml(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '<') || preg_match('/<(html|body|div|meta|p)\b/i', $trimmed) === 1;
    }

    private function htmlToPlainText(string $html): string
    {
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<(br|BR)\s*\/?>/', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|tr|li|h[1-6])>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<li[^>]*>/i', '- ', $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function tokenRequest(array $payload): array
    {
        $response = Http::asForm()->post($this->accountsBase().'/oauth/v2/token', array_merge($payload, [
            'client_id' => config('helpdesk.mail_oauth.zoho.client_id'),
            'client_secret' => config('helpdesk.mail_oauth.zoho.client_secret'),
        ]));

        if (! $response->successful()) {
            throw new InvalidArgumentException($response->json('error') ?? 'Zoho token request failed.');
        }

        return $response->json();
    }

    private function accountsBase(): string
    {
        return 'https://accounts.zoho.'.config('helpdesk.mail_oauth.zoho.region', 'com');
    }

    private function mailBase(): string
    {
        return 'https://mail.zoho.'.config('helpdesk.mail_oauth.zoho.region', 'com');
    }
}
