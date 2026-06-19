<?php

namespace App\Domains\Tenancy\Jobs;

use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

class VerifyTenantInfrastructureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $tenantId)
    {
    }

    public function handle(TenantInfrastructureService $infrastructure): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        try {
            $infrastructure->verify($tenant);
        } catch (ValidationException) {
        }
    }
}
