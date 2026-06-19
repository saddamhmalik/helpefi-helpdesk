<?php

namespace App\Domains\Tenancy\Repositories;

use App\Domains\Tenancy\Models\TenantInfrastructureBackup;
use Illuminate\Support\Collection;

class TenantInfrastructureBackupRepository
{
    public function forTenant(string $tenantId): Collection
    {
        return TenantInfrastructureBackup::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('stored_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForTenant(string $tenantId, string $id): ?TenantInfrastructureBackup
    {
        return TenantInfrastructureBackup::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();
    }

    public function findByObjectKey(string $tenantId, string $objectKey): ?TenantInfrastructureBackup
    {
        return TenantInfrastructureBackup::query()
            ->where('tenant_id', $tenantId)
            ->where('object_key', $objectKey)
            ->first();
    }

    public function upsertFromObject(
        string $tenantId,
        string $objectKey,
        int $size,
        ?\DateTimeInterface $storedAt = null,
        ?string $label = null,
    ): TenantInfrastructureBackup {
        $backup = $this->findByObjectKey($tenantId, $objectKey)
            ?? new TenantInfrastructureBackup([
                'tenant_id' => $tenantId,
                'object_key' => $objectKey,
            ]);

        $backup->size = $size;
        $backup->stored_at = $storedAt ?? now();

        if ($label !== null) {
            $backup->label = $label;
        } elseif (! filled($backup->label)) {
            $backup->label = basename($objectKey);
        }

        $backup->save();

        return $backup->fresh();
    }

    public function save(TenantInfrastructureBackup $backup): TenantInfrastructureBackup
    {
        $backup->save();

        return $backup->fresh();
    }

    public function delete(TenantInfrastructureBackup $backup): void
    {
        $backup->delete();
    }

    public function deleteMissingKeys(string $tenantId, array $existingKeys): int
    {
        if ($existingKeys === []) {
            return TenantInfrastructureBackup::query()
                ->where('tenant_id', $tenantId)
                ->delete();
        }

        return TenantInfrastructureBackup::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('object_key', $existingKeys)
            ->delete();
    }
}
