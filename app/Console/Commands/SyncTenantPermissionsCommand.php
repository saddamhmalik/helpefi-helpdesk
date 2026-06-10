<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncTenantPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync {tenant? : Tenant id or slug (all tenants when omitted)}';

    protected $description = 'Sync agent role permissions for tenant workspace databases';

    public function handle(): int
    {
        $identifier = $this->argument('tenant');

        if ($identifier) {
            $tenant = Tenant::query()
                ->where('id', $identifier)
                ->orWhere('slug', $identifier)
                ->first();

            if (! $tenant) {
                $this->error("No tenant matched [{$identifier}].");

                return self::FAILURE;
            }

            $params = [
                '--tenants' => [$tenant->id],
                '--class' => PermissionSeeder::class,
                '--force' => true,
            ];
        } else {
            $params = [
                '--class' => PermissionSeeder::class,
                '--force' => true,
            ];
        }

        $exitCode = Artisan::call('tenants:seed', $params, $this->output);

        if ($exitCode === self::SUCCESS) {
            $this->info('Tenant permissions synced.');
        }

        return $exitCode;
    }
}
