<?php

namespace App\Console\Commands;

use App\Domains\Channels\Services\InboundMailboxPollService;
use Illuminate\Console\Command;

class PollEmailInboxesCommand extends Command
{
    protected $signature = 'channels:poll-inboxes';

    protected $description = 'Poll configured IMAP/POP3 mailboxes and import new messages as tickets';

    public function handle(InboundMailboxPollService $pollService): int
    {
        $result = $pollService->pollAll();

        $this->info("Polled {$result['polled']} mailbox(es).");

        foreach ($result['stats'] ?? [] as $address => $stats) {
            $this->line(sprintf(
                '  %s: fetched=%d created=%d replies=%d duplicates=%d ignored=%d failed=%d',
                $address,
                $stats['fetched'],
                $stats['created'],
                $stats['reply'],
                $stats['duplicate'],
                $stats['ignored'] ?? 0,
                $stats['failed'],
            ));
        }

        foreach ($result['errors'] as $inbox => $message) {
            $this->warn("{$inbox}: {$message}");
        }

        return self::SUCCESS;
    }
}
