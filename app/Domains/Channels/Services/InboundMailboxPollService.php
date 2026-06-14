<?php

namespace App\Domains\Channels\Services;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Repositories\EmailInboxRepository;
use App\Domains\Channels\Services\Mailbox\MailboxReaderFactory;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class InboundMailboxPollService
{
    public function __construct(
        private EmailInboxRepository $inboxes,
        private MailboxReaderFactory $readers,
        private ChannelService $channels,
    ) {
    }

    public function pollAll(): array
    {
        $polled = 0;
        $errors = [];
        $stats = [];

        foreach ($this->inboxes->pollable() as $inbox) {
            try {
                $stats[$inbox->address] = $this->pollInbox($inbox);
                $polled++;
            } catch (Throwable $exception) {
                $label = $inbox->name ?: $inbox->address;
                $errors[$label] = $exception->getMessage();
                Log::warning('Mailbox poll failed.', [
                    'inbox' => $label,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return ['polled' => $polled, 'errors' => $errors, 'stats' => $stats];
    }

    public function pollInbox(EmailInbox $inbox): array
    {
        $this->assertPollable($inbox);

        try {
            $reader = $this->readers->forInbox($inbox);
            $messages = $reader->fetch($inbox);
            $stats = [
                'fetched' => count($messages),
                'created' => 0,
                'reply' => 0,
                'duplicate' => 0,
                'ignored' => 0,
                'failed' => 0,
            ];
            $processed = $inbox->mailbox_processed_ids ?? [];
            $failures = [];

            foreach ($messages as $message) {
                try {
                    $result = $this->channels->processInboundEmail(
                        $message->toPayload(),
                        $inbox->inbound_token,
                        fromPoll: true,
                    );

                    $action = $result['action'] ?? null;

                    if ($message->pollUid) {
                        $processed[] = $message->pollUid;
                    }

                    $reader->markMessageProcessed($inbox, $message);

                    match ($action) {
                        'created' => $stats['created']++,
                        'reply', 'side_reply' => $stats['reply']++,
                        'duplicate' => $stats['duplicate']++,
                        'ignored', 'blocked' => $stats['ignored']++,
                        default => null,
                    };
                } catch (Throwable $exception) {
                    $stats['failed']++;
                    $failures[] = $exception->getMessage();

                    Log::warning('Mailbox message import failed.', [
                        'inbox' => $inbox->address,
                        'from' => $message->fromEmail,
                        'subject' => $message->subject,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $pollError = $this->summarizeFailures($failures);

            $inbox->update([
                'last_polled_at' => now(),
                'poll_error' => $pollError,
                'mailbox_processed_ids' => array_values(array_slice(array_unique($processed), -5000)),
            ]);

            return $stats;
        } catch (InvalidArgumentException $exception) {
            $inbox->update([
                'last_polled_at' => now(),
                'poll_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function testConnection(EmailInbox $inbox): void
    {
        $this->assertPollable($inbox, requirePassword: true);
        $this->readers->forInbox($inbox)->testConnection($inbox);
    }

    private function summarizeFailures(array $failures): ?string
    {
        if ($failures === []) {
            return null;
        }

        $unique = array_values(array_unique($failures));

        return count($failures).' message(s) could not be imported: '.implode(' | ', array_slice($unique, 0, 2));
    }

    private function assertPollable(EmailInbox $inbox, bool $requirePassword = false): void
    {
        if (! $inbox->is_active) {
            throw new InvalidArgumentException('Inbox is not active.');
        }

        if ($inbox->inbound_method === 'oauth') {
            if (! $inbox->oauth_provider || ! $inbox->oauth_refresh_token) {
                throw new InvalidArgumentException('Connect a Google, Microsoft, or Zoho account first.');
            }

            return;
        }

        if ($inbox->inbound_method !== 'poll') {
            throw new InvalidArgumentException('This inbox does not use mailbox polling.');
        }

        if (! $inbox->mailbox_protocol || ! $inbox->mailbox_host) {
            throw new InvalidArgumentException('Mailbox connection is not configured.');
        }

        if ($requirePassword && ! $inbox->mailbox_password) {
            throw new InvalidArgumentException('Mailbox password is not configured.');
        }
    }
}
