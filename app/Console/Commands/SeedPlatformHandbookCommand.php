<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\PlatformHandbookSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedPlatformHandbookCommand extends Command
{
    protected $signature = 'helpdesk:seed-handbook {tenant? : Tenant id or slug (all tenants when omitted)}';

    protected $description = 'Seed or refresh the permanent How to use helpefi handbook';

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
                '--class' => PlatformHandbookSeeder::class,
                '--force' => true,
            ];
        } else {
            $params = [
                '--class' => PlatformHandbookSeeder::class,
                '--force' => true,
            ];
        }

        $exitCode = Artisan::call('tenants:seed', $params, $this->output);

        if ($exitCode === self::SUCCESS) {
            $this->info('Platform handbook seeded.');
        }

        return $exitCode;
    }
}
