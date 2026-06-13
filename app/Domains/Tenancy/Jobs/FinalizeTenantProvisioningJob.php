<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Platform\Services\PlatformMailService;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Domains\Tenancy\Services\TenantRouteRegistryService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ProductKnowledgeSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class FinalizeTenantProvisioningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Tenant $tenant)
    {
    }

    public function handle(
        TenantProvisioningService $provisioning,
        TenantRouteRegistryService $tenantRoutes,
        PlatformMailService $platformMail,
    ): void
    {
        $data = $this->tenant->data ?? [];

        tenancy()->initialize($this->tenant);

        Artisan::call('db:seed', [
            '--class' => TenantBootstrapSeeder::class,
            '--force' => true,
        ]);

        $adminEmail = $this->tenant->admin_email ?? $data['admin_email'] ?? 'admin@helpdesk.test';
        $adminName = $this->tenant->admin_name ?? $data['admin_name'] ?? 'Admin User';
        $adminPassword = $this->tenant->admin_password ?? $data['admin_password'] ?? bcrypt('password');

        $admin = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => $adminPassword,
            ],
        );
        $admin->assignRole('admin');

        Artisan::call('db:seed', [
            '--class' => WorkforceSeeder::class,
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => ProductKnowledgeSeeder::class,
            '--force' => true,
        ]);

        $tenantRoutes->syncCurrentTenant();

        tenancy()->end();

        $provisioning->ensureCentralSubscription($this->tenant);

        $platformMail->sendWorkspaceWelcome(
            $this->tenant,
            $adminName,
            $adminEmail,
            $provisioning->welcomeUrl($this->tenant, $adminEmail),
        );
    }
}
