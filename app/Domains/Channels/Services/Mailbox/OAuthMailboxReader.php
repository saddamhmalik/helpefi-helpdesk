<?php

namespace App\Domains\Channels\Services\Mailbox;

use App\Domains\Channels\Contracts\MailboxReaderInterface;
use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Services\OAuth\MailOAuthService;
use InvalidArgumentException;

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
        $messages = $provider->fetchUnreadMessages($inbox, $token);

        foreach ($messages as $message) {
            $provider->markMessageProcessed($inbox, $token, $message);
        }

        return $messages;
    }
}
