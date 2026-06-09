<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformPermission;
use App\Domains\Platform\Models\PlatformRole;
use Illuminate\Database\Eloquent\Collection;

class PlatformRoleRepository
{
    public function allWithPermissions(): Collection
    {
        return PlatformRole::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function assignable(): Collection
    {
        return PlatformRole::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function find(int $id): PlatformRole
    {
        return PlatformRole::query()
            ->with('permissions:id,name')
            ->findOrFail($id);
    }

    public function findByName(string $name): ?PlatformRole
    {
        return PlatformRole::query()
            ->where('name', $name)
            ->first();
    }

    public function create(string $name): PlatformRole
    {
        return PlatformRole::query()->create([
            'name' => $name,
            'guard_name' => 'platform',
        ]);
    }

    public function update(PlatformRole $role, array $data): PlatformRole
    {
        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->save();
        }

        return $role->fresh(['permissions:id,name']);
    }

    public function syncPermissions(PlatformRole $role, array $permissionNames): PlatformRole
    {
        $permissionIds = PlatformPermission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        return $role->fresh(['permissions:id,name']);
    }

    public function delete(PlatformRole $role): void
    {
        $role->delete();
    }

    public function permissionExists(string $name): bool
    {
        return PlatformPermission::query()
            ->where('name', $name)
            ->exists();
    }
}
