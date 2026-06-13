<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\PlatformPendingRegistrationService;
use Illuminate\Console\Command;

class PurgeExpiredPendingRegistrationsCommand extends Command
{
    protected $signature = 'registrations:purge-expired';

    protected $description = 'Delete unverified pending registrations whose verification link has expired';

    public function handle(PlatformPendingRegistrationService $pendingRegistrations): int
    {
        $removed = $pendingRegistrations->purgeExpired();

        if ($removed === 0) {
            $this->info('No expired pending registrations to purge.');

            return self::SUCCESS;
        }

        $this->info("Purged {$removed} expired pending registration(s).");

        return self::SUCCESS;
    }
}
