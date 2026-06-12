<?php

namespace App\Domains\Tenancy\Repositories;

use App\Models\Tenant;
use App\Models\TenantDomain;

class TenantDomainRepository
{
    public function forTenant(Tenant $tenant): \Illuminate\Database\Eloquent\Collection
    {
        return TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_primary')
            ->orderBy('type')
            ->get();
    }

    public function findForTenant(Tenant $tenant, int $id): TenantDomain
    {
        return TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function findByHost(string $host): ?TenantDomain
    {
        return TenantDomain::query()
            ->where('domain', strtolower($host))
            ->first();
    }

    public function hostExists(string $host): bool
    {
        return TenantDomain::query()
            ->where('domain', strtolower($host))
            ->exists();
    }

    public function platformDomain(Tenant $tenant): ?TenantDomain
    {
        return TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', TenantDomain::TYPE_PLATFORM)
            ->first();
    }

    public function customDomain(Tenant $tenant): ?TenantDomain
    {
        return TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', TenantDomain::TYPE_CUSTOM)
            ->first();
    }

    public function primaryDomain(Tenant $tenant): ?TenantDomain
    {
        $primary = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_primary', true)
            ->first();

        if ($primary?->isPlatform() || $primary?->isVerified()) {
            return $primary;
        }

        return $this->platformDomain($tenant);
    }

    public function createPlatform(Tenant $tenant, string $host): TenantDomain
    {
        return TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => strtolower($host),
            'type' => TenantDomain::TYPE_PLATFORM,
            'is_primary' => true,
            'verification_status' => TenantDomain::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }

    public function createCustom(Tenant $tenant, string $host, string $token): TenantDomain
    {
        return TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => strtolower($host),
            'type' => TenantDomain::TYPE_CUSTOM,
            'is_primary' => false,
            'verification_status' => TenantDomain::STATUS_PENDING,
            'verification_token' => $token,
        ]);
    }

    public function update(TenantDomain $domain, array $data): TenantDomain
    {
        $domain->update($data);

        return $domain->fresh();
    }

    public function markVerified(TenantDomain $domain): TenantDomain
    {
        return $this->update($domain, [
            'verification_status' => TenantDomain::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }

    public function markFailed(TenantDomain $domain): TenantDomain
    {
        return $this->update($domain, [
            'verification_status' => TenantDomain::STATUS_FAILED,
        ]);
    }

    public function setPrimary(Tenant $tenant, TenantDomain $domain): void
    {
        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->update(['is_primary' => false]);

        $this->update($domain, ['is_primary' => true]);
    }

    public function deleteCustom(Tenant $tenant): void
    {
        $platform = $this->platformDomain($tenant);

        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', TenantDomain::TYPE_CUSTOM)
            ->delete();

        if ($platform) {
            $this->update($platform, ['is_primary' => true]);
        }
    }
}
