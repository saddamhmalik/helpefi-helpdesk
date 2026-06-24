<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\TenantReleaseUpgradeService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class UpgradeTenantReleasesCommand extends Command
{
    protected $signature = 'tenants:upgrade
        {tenant? : Tenant id or slug (all tenants when omitted)}
        {--skip-handbook : Skip platform handbook sync step}
        {--skip-cache : Skip workspace cache clear step}
        {--skip-permissions : Skip agent permission sync step}
        {--status : Show pending release steps without running them}';

    protected $description = 'Apply versioned tenant data release upgrades for each workspace';

    public function handle(TenantReleaseUpgradeService $upgrades): int
    {
        $identifier = $this->argument('tenant');
        $tenants = $identifier
            ? Tenant::query()
                ->where('id', $identifier)
                ->orWhere('slug', $identifier)
                ->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($identifier ? "No tenant matched [{$identifier}]." : 'No tenants found.');

            return self::FAILURE;
        }

        $options = [
            'skip_handbook' => (bool) $this->option('skip-handbook'),
            'skip_cache' => (bool) $this->option('skip-cache'),
            'skip_permissions' => (bool) $this->option('skip-permissions'),
        ];

        $failures = 0;
        $target = $upgrades->targetRelease();
        $this->line("Target application release: <info>{$target}</info>");

        foreach ($tenants as $tenant) {
            if ($this->option('status')) {
                $status = $upgrades->status($tenant);
                $this->renderStatus($tenant->slug, $status);

                continue;
            }

            $pending = $upgrades->pendingStepCount($tenant);

            if ($pending < 1) {
                $completed = $upgrades->highestCompletedRelease($tenant) ?? $tenant->release_version ?? $target;
                $this->components->twoColumnDetail(
                    $tenant->slug,
                    "up to date ({$completed})",
                );

                continue;
            }

            $this->line("Upgrading workspace <info>{$tenant->slug}</info> ({$pending} step(s))...");

            try {
                $result = $upgrades->upgradeTenant($tenant, $options);

                $this->components->twoColumnDetail(
                    $tenant->slug,
                    sprintf(
                        'release=%s steps=%d batch=%s',
                        $result['completed_release'] ?? 'n/a',
                        count($result['ran_steps']),
                        $result['batch'] ?? 'n/a',
                    ),
                );
            } catch (\Throwable $exception) {
                $failures++;
                $this->error("Failed for {$tenant->slug}: {$exception->getMessage()}");
            }
        }

        if ($this->option('status')) {
            return self::SUCCESS;
        }

        if ($failures > 0) {
            $this->error("{$failures} workspace release upgrade(s) failed.");

            return self::FAILURE;
        }

        $this->info('Tenant release upgrades completed.');

        return self::SUCCESS;
    }

    private function renderStatus(string $slug, array $status): void
    {
        $this->components->twoColumnDetail(
            $slug,
            sprintf(
                'current=%s target=%s pending=%d',
                $status['current_release'] ?? 'none',
                $status['target_release'],
                $status['pending_count'],
            ),
        );

        foreach ($status['pending_steps'] as $step) {
            $this->line("  - {$step['release']}:{$step['step']} — {$step['description']}");
        }
    }
}
