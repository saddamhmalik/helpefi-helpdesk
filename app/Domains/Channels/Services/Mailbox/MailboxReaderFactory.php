<?php

namespace App\Domains\Channels\Services\Mailbox;

use App\Domains\Channels\Contracts\MailboxReaderInterface;
use App\Domains\Channels\Models\EmailInbox;
use InvalidArgumentException;

class MailboxReaderFactory
{
    public function forInbox(EmailInbox $inbox): MailboxReaderInterface
    {
        if ($inbox->inbound_method === 'oauth') {
            return app(OAuthMailboxReader::class);
        }

        return match ($inbox->mailbox_protocol) {
            'imap' => app(ImapMailboxReader::class),
            'pop3' => app(Pop3MailboxReader::class),
            default => throw new InvalidArgumentException('Unsupported mailbox protocol.'),
        };
    }
}
