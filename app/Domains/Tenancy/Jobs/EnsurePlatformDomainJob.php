<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Tenancy\Repositories\TenantDomainRepository;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnsurePlatformDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Tenant $tenant)
    {
    }

    public function handle(
        TenantDomainRepository $domains,
        TenantProvisioningService $provisioning,
    ): void {
        if ($this->tenant->domains()->exists()) {
            return;
        }

        $slug = $this->tenant->slug;

        if ($slug === null || $slug === '') {
            return;
        }

        $domains->createPlatform($this->tenant, $provisioning->tenantDomain($slug));
    }
}
