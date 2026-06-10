<?php

namespace App\Console\Commands;

use App\Domains\Tickets\Services\TicketSnoozeService;
use Illuminate\Console\Command;

class UnsnoozeTicketsCommand extends Command
{
    protected $signature = 'tickets:unsnooze';

    protected $description = 'Clear expired ticket snoozes';

    public function handle(TicketSnoozeService $snoozes): int
    {
        $count = $snoozes->releaseExpired();

        if ($count > 0) {
            $this->info("Released {$count} snoozed ticket(s).");
        }

        return self::SUCCESS;
    }
}
