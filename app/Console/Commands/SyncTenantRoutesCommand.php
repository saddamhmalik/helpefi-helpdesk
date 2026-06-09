<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\TenantRouteRegistryService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncTenantRoutesCommand extends Command
{
    protected $signature = 'tenants:sync-routes {tenant? : Tenant slug or id}';

    protected $description = 'Sync central route mappings for widget keys and inbound email routes';

    public function handle(TenantRouteRegistryService $registry): int
    {
        $identifier = $this->argument('tenant');

        $tenants = $identifier
            ? Tenant::query()->where('id', $identifier)->orWhere('slug', $identifier)->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants matched.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $registry->syncTenant($tenant);
            $this->line("Synced routes for [{$tenant->slug}] ({$tenant->id})");
        }

        $this->info('Tenant route sync complete.');

        return self::SUCCESS;
    }
}
