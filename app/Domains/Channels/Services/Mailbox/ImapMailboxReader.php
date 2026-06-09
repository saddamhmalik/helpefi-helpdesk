<?php

namespace App\Domains\Channels\Services\Mailbox;

use App\Domains\Channels\Contracts\MailboxReaderInterface;
use App\Domains\Channels\Data\InboundMailMessage;
use App\Domains\Channels\Models\EmailInbox;
use InvalidArgumentException;

class ImapMailboxReader implements MailboxReaderInterface
{
    public function testConnection(EmailInbox $inbox): void
    {
        if (function_exists('imap_open')) {
            $this->withNativeConnection($inbox, fn () => null);

            return;
        }

        $connection = $this->connect(MailboxConnectionConfig::fromInbox($inbox));

        try {
            $this->command($connection, 'NOOP');
        } finally {
            $this->logout($connection);
        }
    }

    public function fetch(EmailInbox $inbox): array
    {
        if (function_exists('imap_open')) {
            return $this->fetchNative($inbox);
        }

        return $this->fetchSocket($inbox);
    }

    private function fetchNative(EmailInbox $inbox): array
    {
        $processed = $inbox->mailbox_processed_ids ?? [];
        $messages = [];

        $this->withNativeConnection($inbox, function ($connection) use (&$messages, $processed) {
            $uids = imap_search($connection, 'UNSEEN', SE_UID) ?: [];

            foreach ($uids as $uid) {
                $uidKey = (string) $uid;

                if (in_array($uidKey, $processed, true)) {
                    continue;
                }

                $raw = imap_fetchheader($connection, $uid, FT_UID).imap_body($connection, $uid, FT_UID | FT_PEEK);
                $messages[] = InboundMailParser::parse($raw, $uidKey);
                imap_setflag_full($connection, (string) $uid, '\\Seen', ST_UID);
            }
        });

        return $messages;
    }

    private function withNativeConnection(EmailInbox $inbox, callable $callback)
    {
        $config = MailboxConnectionConfig::fromInbox($inbox);
        $mailbox = sprintf('{%s:%d%s}%s', $config->host, $config->port, $config->imapFlags(), $config->folder);
        $connection = @imap_open($mailbox, $config->username, $config->password);

        if (! $connection) {
            throw new InvalidArgumentException('IMAP connection failed: '.imap_last_error());
        }

        try {
            return $callback($connection);
        } finally {
            imap_close($connection);
        }
    }

    private function fetchSocket(EmailInbox $inbox): array
    {
        $config = MailboxConnectionConfig::fromInbox($inbox);
        $connection = $this->connect($config);
        $processed = $inbox->mailbox_processed_ids ?? [];
        $messages = [];

        try {
            $this->command($connection, 'SELECT "'.$this->escape($config->folder).'"');
            $search = $this->command($connection, 'UID SEARCH UNSEEN');
            $uids = $this->parseSearchUids($search);

            foreach ($uids as $uid) {
                if (in_array($uid, $processed, true)) {
                    continue;
                }

                $fetch = $this->command($connection, "UID FETCH {$uid} (BODY.PEEK[])");
                $raw = $this->extractLiteral($fetch);

                if ($raw === null) {
                    continue;
                }

                $messages[] = InboundMailParser::parse($raw, $uid);
                $this->command($connection, "UID STORE {$uid} +FLAGS (\\Seen)");
            }
        } finally {
            $this->logout($connection);
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
            throw new InvalidArgumentException("IMAP connection failed: {$errorMessage}");
        }

        stream_set_timeout($connection, 20);
        $this->expectTag($connection, '* OK');

        if ($config->usesStartTls()) {
            $this->command($connection, 'STARTTLS');
            stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        $this->command($connection, 'LOGIN "'.$this->escape($config->username).'" "'.$this->escape($config->password).'"');

        return $connection;
    }

    private function logout($connection): void
    {
        try {
            $this->command($connection, 'LOGOUT');
        } finally {
            fclose($connection);
        }
    }

    private function command($connection, string $command): string
    {
        $tag = 'A'.bin2hex(random_bytes(3));
        $this->send($connection, "{$tag} {$command}");

        return $this->readTaggedResponse($connection, $tag);
    }

    private function readTaggedResponse($connection, string $tag): string
    {
        $parts = [];

        while (true) {
            $line = $this->readLine($connection);
            $parts[] = $line;

            if (preg_match('/\{(\d+)\+\}$/', $line, $matches)) {
                $parts[] = $this->readBytes($connection, (int) $matches[1]);

                continue;
            }

            if (preg_match('/\{(\d+)\}$/', $line, $matches)) {
                $parts[] = $this->readBytes($connection, (int) $matches[1]);

                continue;
            }

            if (str_starts_with($line, $tag.' OK') || str_starts_with($line, $tag.' NO') || str_starts_with($line, $tag.' BAD')) {
                break;
            }
        }

        $response = implode("\n", $parts);

        if (! str_contains($response, $tag.' OK')) {
            $summary = strlen($response) > 200 ? substr($response, 0, 200).'…' : $response;

            if (str_contains($response, 'AUTHENTICATIONFAILED') || str_contains($response, 'Invalid credentials')) {
                throw new InvalidArgumentException(
                    'IMAP login failed: Gmail rejected the username or password. Enable IMAP in Gmail settings, turn on 2-Step Verification, then create a Google App Password at https://myaccount.google.com/apppasswords and paste the 16-character code (not your normal Gmail password).'
                );
            }

            throw new InvalidArgumentException("IMAP command failed: {$summary}");
        }

        return $response;
    }

    private function readBytes($connection, int $length): string
    {
        if ($length === 0) {
            return '';
        }

        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($connection, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new InvalidArgumentException('IMAP connection closed while reading message data.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function parseSearchUids(string $response): array
    {
        if (! preg_match('/^\* SEARCH(.*)$/im', $response, $matches)) {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/', trim($matches[1])),
            fn (string $part) => $part !== '' && ctype_digit($part),
        ));
    }

    private function extractLiteral(string $response): ?string
    {
        if (! preg_match('/\{(\d+)\}\r?\n/s', $response, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $length = (int) $matches[1][0];
        $start = $matches[0][1] + strlen($matches[0][0]);

        return substr($response, $start, $length) ?: null;
    }

    private function expectTag($connection, string $prefix): void
    {
        $response = $this->readLine($connection);

        if (! str_starts_with($response, $prefix)) {
            throw new InvalidArgumentException("Unexpected IMAP response: {$response}");
        }
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function send($connection, string $command): void
    {
        fwrite($connection, $command."\r\n");
    }

    private function readLine($connection): string
    {
        $line = fgets($connection);

        if ($line === false) {
            throw new InvalidArgumentException('IMAP connection closed unexpectedly.');
        }

        return rtrim($line, "\r\n");
    }
}
