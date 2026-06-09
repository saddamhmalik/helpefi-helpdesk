<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantBootstrapSeeder;
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

    public function handle(TenantProvisioningService $provisioning): void
    {
        $data = $this->tenant->data ?? [];

        tenancy()->initialize($this->tenant);

        Artisan::call('db:seed', [
            '--class' => TenantBootstrapSeeder::class,
            '--force' => true,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => $data['admin_email'] ?? 'admin@helpdesk.test'],
            [
                'name' => $data['admin_name'] ?? 'Admin User',
                'password' => $data['admin_password'] ?? bcrypt('password'),
            ],
        );
        $admin->assignRole('admin');

        tenancy()->end();

        $provisioning->createCentralSubscription(
            $this->tenant,
            $data['plan'] ?? config('billing.default_plan', 'professional'),
        );
    }
}
