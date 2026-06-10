<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\TenantPurgeService;
use Illuminate\Console\Command;

class PurgeExpiredTenantsCommand extends Command
{
    protected $signature = 'tenants:purge-expired {--dry-run : List workspaces that would be deleted without removing them}';

    protected $description = 'Delete workspaces whose trial or paid access expired beyond the configured grace period';

    public function handle(TenantPurgeService $purge): int
    {
        if (! $purge->isEnabled()) {
            $this->warn('Automatic tenant purge is disabled in platform settings.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $removed = $purge->purgeExpired($dryRun);

        if ($removed === []) {
            $this->info('No expired workspaces to purge.');

            return self::SUCCESS;
        }

        $this->table(
            ['Workspace', 'Slug', 'Database', 'Expired at'],
            collect($removed)->map(fn (array $row) => [
                $row['name'],
                $row['slug'],
                $row['database'],
                $row['expired_at'] ?? '—',
            ])->all(),
        );

        $this->info($dryRun
            ? count($removed).' workspace(s) would be purged.'
            : count($removed).' workspace(s) purged.');

        return self::SUCCESS;
    }
}
