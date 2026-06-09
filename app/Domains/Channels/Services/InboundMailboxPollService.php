<?php

namespace App\Domains\Channels\Services;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Repositories\EmailInboxRepository;
use App\Domains\Channels\Services\Mailbox\MailboxReaderFactory;
use InvalidArgumentException;

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

        foreach ($this->inboxes->pollable() as $inbox) {
            try {
                $this->pollInbox($inbox);
                $polled++;
            } catch (InvalidArgumentException $exception) {
                $errors[$inbox->name ?: $inbox->address] = $exception->getMessage();
            }
        }

        return ['polled' => $polled, 'errors' => $errors];
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
                'failed' => 0,
            ];
            $processed = $inbox->mailbox_processed_ids ?? [];

            foreach ($messages as $message) {
                try {
                    $result = $this->channels->processInboundEmail(
                        $message->toPayload(),
                        $inbox->inbound_token,
                        fromPoll: true,
                    );

                    if ($message->pollUid) {
                        $processed[] = $message->pollUid;
                    }

                    match ($result['action']) {
                        'created' => $stats['created']++,
                        'reply' => $stats['reply']++,
                        'duplicate' => $stats['duplicate']++,
                        default => null,
                    };
                } catch (InvalidArgumentException) {
                    $stats['failed']++;
                }
            }

            $inbox->update([
                'last_polled_at' => now(),
                'poll_error' => null,
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
