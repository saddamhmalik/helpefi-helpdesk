<?php

namespace App\Domains\Channels\Services\Mailbox;

use App\Domains\Channels\Contracts\MailboxReaderInterface;
use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\OAuth\MailOAuthService;

class OAuthMailboxReader implements MailboxReaderInterface
{
    public function __construct(private MailOAuthService $oauth)
    {
    }

    public function testConnection(EmailInbox $inbox): void
    {
        $provider = $this->oauth->providerForInbox($inbox);
        $token = $this->oauth->accessToken($inbox);
        $provider->connectedEmail($token);
    }

    public function fetch(EmailInbox $inbox): array
    {
        $provider = $this->oauth->providerForInbox($inbox);
        $token = $this->oauth->accessToken($inbox);

        return $provider->fetchUnreadMessages($inbox, $token);
    }

    public function markMessageProcessed(EmailInbox $inbox, InboundMailMessage $message): void
    {
        if (! $message->pollUid) {
            return;
        }

        $provider = $this->oauth->providerForInbox($inbox);
        $token = $this->oauth->accessToken($inbox);
        $provider->markMessageProcessed($inbox, $token, $message);
    }
}
