<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\TenantProvisioningService;
use Illuminate\Console\Command;

class ProvisionDemoTenantCommand extends Command
{
    protected $signature = 'tenants:provision-demo {slug=demo : Workspace slug}';

    protected $description = 'Provision a demo tenant with default admin credentials';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $slug = $this->argument('slug');

        if (\App\Models\Tenant::query()->where('slug', $slug)->exists()) {
            $this->error("Tenant [{$slug}] already exists.");

            return self::FAILURE;
        }

        $tenant = $provisioning->provision(
            organizationName: 'Demo helpefi',
            slug: $slug,
            adminName: 'Admin User',
            adminEmail: 'admin@helpdesk.test',
            adminPassword: 'password',
        );

        $provisioning->createCentralSubscription($tenant);

        $this->info('Tenant provisioned: '.$provisioning->tenantUrl($tenant));
        $this->line('Admin: admin@helpdesk.test / password');

        return self::SUCCESS;
    }
}
