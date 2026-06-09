<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\PlatformBackupScheduleService;
use App\Domains\Platform\Services\PlatformBackupService;
use App\Models\PlatformUser;
use Illuminate\Console\Command;

class RunPlatformBackupsCommand extends Command
{
    protected $signature = 'platform:run-backups';

    protected $description = 'Create scheduled central and workspace database backups';

    public function handle(
        PlatformBackupService $backups,
        PlatformBackupScheduleService $schedule,
    ): int {
        if (! $schedule->isEnabled()) {
            $this->info('Scheduled backups are disabled.');

            return self::SUCCESS;
        }

        $actor = PlatformUser::query()->where('is_active', true)->orderBy('id')->first();

        if (! $actor) {
            $this->error('No active platform user found to attribute scheduled backups.');

            return self::FAILURE;
        }

        $backups->queueCentral($actor);
        $queued = $backups->queueAllTenants($actor);
        $purged = $backups->purgeExpired();

        $this->info('Central backup queued.');
        $this->info(count($queued).' workspace backup(s) queued.');
        $this->info("Purged {$purged} expired backup(s).");

        return self::SUCCESS;
    }
}
