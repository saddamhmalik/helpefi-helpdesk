<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\TenantReleaseUpgradeService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class UpgradeTenantWorkspacesCommand extends Command
{
    protected $signature = 'helpdesk:upgrade-workspaces
        {tenant? : Tenant id or slug (all tenants when omitted)}
        {--skip-handbook : Do not seed or refresh the platform handbook}
        {--skip-cache : Do not clear tenant reference and help center caches}';

    protected $description = 'Deprecated alias for tenants:upgrade';

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
        ];

        $failures = 0;

        foreach ($tenants as $tenant) {
            try {
                $upgrades->upgradeTenant($tenant, $options);
            } catch (\Throwable $exception) {
                $failures++;
                $this->error("Failed for {$tenant->slug}: {$exception->getMessage()}");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
