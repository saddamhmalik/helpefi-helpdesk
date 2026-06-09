<?php

namespace App\Domains\Tenancy\Repositories;

use App\Domains\Tenancy\Models\TenantRouteMapping;
use Illuminate\Support\Collection;

class TenantRouteMappingRepository
{
    public function upsert(string $tenantId, string $type, string $lookupKey): TenantRouteMapping
    {
        return TenantRouteMapping::query()->updateOrCreate(
            [
                'type' => $type,
                'lookup_key' => $lookupKey,
            ],
            [
                'tenant_id' => $tenantId,
            ],
        );
    }

    public function deleteByTypeAndKey(string $type, string $lookupKey): void
    {
        TenantRouteMapping::query()
            ->where('type', $type)
            ->where('lookup_key', $lookupKey)
            ->delete();
    }

    public function deleteByTenant(string $tenantId): void
    {
        TenantRouteMapping::query()
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    public function findTenantId(string $type, string $lookupKey): ?string
    {
        return TenantRouteMapping::query()
            ->where('type', $type)
            ->where('lookup_key', $lookupKey)
            ->value('tenant_id');
    }

    public function forTenant(string $tenantId): Collection
    {
        return TenantRouteMapping::query()
            ->where('tenant_id', $tenantId)
            ->get();
    }
}
