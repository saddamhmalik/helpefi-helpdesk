<?php

namespace App\Domains\Channels\Contracts;

use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;

interface MailboxReaderInterface
{
    public function testConnection(EmailInbox $inbox): void;

    public function fetch(EmailInbox $inbox): array;
}
