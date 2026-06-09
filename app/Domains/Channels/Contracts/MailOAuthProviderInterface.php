<?php

namespace App\Domains\Channels\Contracts;

use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;

interface MailOAuthProviderInterface
{
    public function provider(): string;

    public function isConfigured(): bool;

    public function authorizationUrl(string $state, string $redirectUri): string;

    public function exchangeCode(string $code, string $redirectUri): array;

    public function refreshTokens(string $refreshToken): array;

    public function connectedEmail(string $accessToken): ?string;

    public function bootstrapMetadata(string $accessToken): array;

    public function fetchUnreadMessages(EmailInbox $inbox, string $accessToken): array;

    public function markMessageProcessed(EmailInbox $inbox, string $accessToken, InboundMailMessage $message): void;
}
