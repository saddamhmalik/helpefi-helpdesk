<?php

namespace App\Domains\Auth\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function allWithPermissions(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'customer')
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function assignable(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'customer')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function find(int $id): Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->findOrFail($id);
    }

    public function findByName(string $name): ?Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', $name)
            ->first();
    }

    public function create(string $name): Role
    {
        return Role::query()->create([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }

    public function update(Role $role, array $data): Role
    {
        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->save();
        }

        return $role->fresh(['permissions:id,name']);
    }

    public function syncPermissions(Role $role, array $permissionNames): Role
    {
        $role->syncPermissions($permissionNames);

        return $role->fresh(['permissions:id,name']);
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function allPermissionNames(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function permissionExists(string $name): bool
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->where('name', $name)
            ->exists();
    }
}
