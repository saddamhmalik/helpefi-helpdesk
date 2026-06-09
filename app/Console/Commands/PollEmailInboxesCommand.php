<?php

namespace App\Console\Commands;

use App\Domains\Channels\Services\InboundMailboxPollService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollEmailInboxesCommand extends Command
{
    protected $signature = 'channels:poll-inboxes';

    protected $description = 'Poll configured IMAP/POP3 mailboxes and import new messages as tickets';

    public function handle(InboundMailboxPollService $pollService): int
    {
        $result = $pollService->pollAll();

        $this->info("Polled {$result['polled']} mailbox(es).");

        foreach ($result['errors'] as $inbox => $message) {
            $this->warn("{$inbox}: {$message}");
            Log::warning('Mailbox poll failed.', [
                'inbox' => $inbox,
                'message' => $message,
            ]);
        }

        return self::SUCCESS;
    }
}
