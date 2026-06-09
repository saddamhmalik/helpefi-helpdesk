<?php

namespace App\Domains\Channels\Services\Mailbox;

use App\Domains\Channels\Models\EmailInbox;
use InvalidArgumentException;

class MailboxConnectionConfig
{
    public function __construct(
        public readonly string $protocol,
        public readonly string $host,
        public readonly int $port,
        public readonly ?string $encryption,
        public readonly string $username,
        public readonly string $password,
        public readonly string $folder,
    ) {
    }

    public static function fromInbox(EmailInbox $inbox): self
    {
        if (! $inbox->mailbox_host || ! $inbox->mailbox_protocol) {
            throw new InvalidArgumentException('Mailbox connection is not configured.');
        }

        $password = $inbox->mailbox_password;

        if (! $password) {
            throw new InvalidArgumentException('Mailbox password is not configured.');
        }

        return new self(
            protocol: $inbox->mailbox_protocol,
            host: $inbox->mailbox_host,
            port: $inbox->mailbox_port ?? self::defaultPort($inbox->mailbox_protocol, $inbox->mailbox_encryption),
            encryption: $inbox->mailbox_encryption,
            username: $inbox->mailbox_username ?: $inbox->address,
            password: $password,
            folder: $inbox->mailbox_folder ?: 'INBOX',
        );
    }

    public function socketAddress(): string
    {
        $scheme = match ($this->effectiveEncryption()) {
            'ssl' => 'ssl',
            'tls' => 'tcp',
            default => 'tcp',
        };

        return "{$scheme}://{$this->host}:{$this->port}";
    }

    public function imapFlags(): string
    {
        return match ($this->effectiveEncryption()) {
            'ssl' => '/imap/ssl/validate-cert',
            'tls' => '/imap/tls/validate-cert',
            default => '/imap/notls',
        };
    }

    public function usesStartTls(): bool
    {
        return $this->effectiveEncryption() === 'tls';
    }

    private function effectiveEncryption(): string
    {
        if ($this->protocol === 'imap' && $this->port === 993) {
            return 'ssl';
        }

        if ($this->protocol === 'pop3' && $this->port === 995) {
            return 'ssl';
        }

        return $this->encryption ?: 'none';
    }

    private static function defaultPort(string $protocol, ?string $encryption): int
    {
        if ($protocol === 'pop3') {
            return $encryption === 'ssl' ? 995 : 110;
        }

        return match ($encryption) {
            'ssl' => 993,
            'tls' => 143,
            default => 143,
        };
    }
}
