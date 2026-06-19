<?php

namespace App\Domains\Tenancy\Repositories;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Models\Tenant;

class TenantInfrastructureRepository
{
    public function findForTenant(string $tenantId): ?TenantInfrastructure
    {
        return TenantInfrastructure::query()
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function findForTenantOrNew(Tenant $tenant): TenantInfrastructure
    {
        return TenantInfrastructure::query()->firstOrNew([
            'tenant_id' => $tenant->id,
        ]);
    }

    public function save(TenantInfrastructure $infrastructure): TenantInfrastructure
    {
        $infrastructure->save();

        return $infrastructure->fresh();
    }

    public function markStatus(
        TenantInfrastructure $infrastructure,
        string $status,
        ?string $message = null,
        bool $touchVerifiedAt = false,
    ): TenantInfrastructure {
        $infrastructure->status = $status;
        $infrastructure->status_message = $message;

        if ($touchVerifiedAt && $status === TenantInfrastructure::STATUS_VERIFIED) {
            $infrastructure->last_verified_at = now();
            $infrastructure->health_failure_count = 0;
        }

        return $this->save($infrastructure);
    }

    public function allExternal(): \Illuminate\Support\Collection
    {
        return TenantInfrastructure::query()
            ->with('tenant')
            ->where(function ($query) {
                $query
                    ->where('database_mode', TenantInfrastructure::MODE_EXTERNAL)
                    ->orWhere('storage_mode', TenantInfrastructure::MODE_EXTERNAL);
            })
            ->get();
    }
}
