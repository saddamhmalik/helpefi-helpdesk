<?php

namespace App\Domains\Channels\Services\Mailbox;

use App\Domains\Channels\Contracts\MailboxReaderInterface;
use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;
use InvalidArgumentException;

class Pop3MailboxReader implements MailboxReaderInterface
{
    public function testConnection(EmailInbox $inbox): void
    {
        $connection = $this->connect(MailboxConnectionConfig::fromInbox($inbox));

        try {
            $this->command($connection, 'NOOP');
        } finally {
            $this->quit($connection);
        }
    }

    public function fetch(EmailInbox $inbox): array
    {
        $config = MailboxConnectionConfig::fromInbox($inbox);
        $connection = $this->connect($config);
        $processed = $inbox->mailbox_processed_ids ?? [];
        $messages = [];

        try {
            foreach ($this->uidl($connection) as $messageNumber => $uid) {
                if (in_array($uid, $processed, true)) {
                    continue;
                }

                $raw = $this->retr($connection, $messageNumber);
                $message = InboundMailParser::parse($raw, $uid);
                $messages[] = $message;
            }
        } finally {
            $this->quit($connection);
        }

        return $messages;
    }

    private function connect(MailboxConnectionConfig $config)
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $connection = @stream_socket_client(
            $config->socketAddress(),
            $errorCode,
            $errorMessage,
            20,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! $connection) {
            throw new InvalidArgumentException("POP3 connection failed: {$errorMessage}");
        }

        stream_set_timeout($connection, 20);
        $this->expectResponse($connection, '+OK');

        $this->command($connection, 'USER '.$config->username);
        $this->command($connection, 'PASS '.$config->password);

        return $connection;
    }

    private function uidl($connection): array
    {
        $this->send($connection, 'UIDL');
        $first = $this->readLine($connection);

        if (! str_starts_with($first, '+OK')) {
            throw new InvalidArgumentException('POP3 UIDL failed.');
        }

        $map = [];

        while ($line = $this->readLine($connection)) {
            if ($line === '.') {
                break;
            }

            [$number, $uid] = array_pad(explode(' ', trim($line), 2), 2, null);

            if ($number && $uid) {
                $map[(int) $number] = $uid;
            }
        }

        return $map;
    }

    private function retr($connection, int $messageNumber): string
    {
        $this->send($connection, 'RETR '.$messageNumber);
        $first = $this->readLine($connection);

        if (! str_starts_with($first, '+OK')) {
            throw new InvalidArgumentException("POP3 RETR {$messageNumber} failed.");
        }

        $lines = [];

        while ($line = $this->readLine($connection)) {
            if ($line === '.') {
                break;
            }

            if (str_starts_with($line, '..')) {
                $line = substr($line, 1);
            }

            $lines[] = $line;
        }

        return implode("\r\n", $lines);
    }

    private function quit($connection): void
    {
        try {
            $this->send($connection, 'QUIT');
            $this->readLine($connection);
        } finally {
            fclose($connection);
        }
    }

    private function command($connection, string $command): string
    {
        $this->send($connection, $command);
        $response = $this->readLine($connection);

        if (! str_starts_with($response, '+OK')) {
            throw new InvalidArgumentException("POP3 command failed: {$response}");
        }

        return $response;
    }

    private function expectResponse($connection, string $prefix): void
    {
        $response = $this->readLine($connection);

        if (! str_starts_with($response, $prefix)) {
            throw new InvalidArgumentException("Unexpected POP3 response: {$response}");
        }
    }

    private function send($connection, string $command): void
    {
        fwrite($connection, $command."\r\n");
    }

    private function readLine($connection): string
    {
        $line = fgets($connection);

        if ($line === false) {
            throw new InvalidArgumentException('POP3 connection closed unexpectedly.');
        }

        return rtrim($line, "\r\n");
    }
}
