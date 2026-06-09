<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Repositories\RoleRepository;
use App\Domains\Security\Support\AuditRecorder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private RoleRepository $roles,
        private AuditRecorder $audit,
    ) {
    }

    public function catalog(): array
    {
        return collect(config('permissions.groups', []))
            ->map(fn (array $permissions, string $group) => [
                'group' => $group,
                'permissions' => collect($permissions)->map(fn (string $label, string $name) => [
                    'name' => $name,
                    'label' => $label,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function list(): Collection
    {
        return $this->roles->allWithPermissions();
    }

    public function assignableRoles(): array
    {
        return $this->roles->assignable()->pluck('name')->all();
    }

    public function show(int $id): Role
    {
        return $this->roles->find($id);
    }

    public function create(string $name, array $permissions): Role
    {
        $normalized = $this->normalizeName($name);
        $this->assertUniqueName($normalized);
        $this->assertValidPermissions($permissions);

        $role = $this->roles->create($normalized);

        $role = $this->roles->syncPermissions($role, $permissions);

        $this->audit->record('role.created', null, [
            'name' => $role->name,
            'permissions' => $permissions,
        ]);

        return $role;
    }

    public function update(int $id, ?string $name, array $permissions): Role
    {
        $role = $this->roles->find($id);
        $this->assertValidPermissions($permissions);

        if ($name !== null) {
            $normalized = $this->normalizeName($name);

            if ($normalized !== $role->name) {
                $this->assertNotProtected($role, 'rename');
                $this->assertUniqueName($normalized, $role->id);
                $role = $this->roles->update($role, ['name' => $normalized]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = $this->roles->syncPermissions($role, $permissions);

        $this->audit->record('role.updated', null, [
            'name' => $role->name,
            'permissions' => $permissions,
        ]);

        return $role;
    }

    public function delete(int $id): void
    {
        $role = $this->roles->find($id);
        $this->assertNotProtected($role, 'delete');

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Cannot delete a role that is assigned to users.',
            ]);
        }

        $name = $role->name;
        $this->roles->delete($role);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->audit->record('role.deleted', null, ['name' => $name]);
    }

    public function isAssignable(string $roleName): bool
    {
        return $roleName !== 'customer' && $this->roles->findByName($roleName) !== null;
    }

    private function normalizeName(string $name): string
    {
        return Str::slug(trim($name), '_');
    }

    private function assertUniqueName(string $name, ?int $ignoreId = null): void
    {
        $existing = $this->roles->findByName($name);

        if ($existing && $existing->id !== $ignoreId) {
            throw ValidationException::withMessages([
                'name' => 'A role with this name already exists.',
            ]);
        }

        if ($name === 'customer') {
            throw ValidationException::withMessages([
                'name' => 'This role name is reserved.',
            ]);
        }
    }

    private function assertValidPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (! $this->roles->permissionExists($permission)) {
                throw ValidationException::withMessages([
                    'permissions' => "Unknown permission: {$permission}",
                ]);
            }
        }
    }

    private function assertNotProtected(Role $role, string $action): void
    {
        if (in_array($role->name, config('permissions.protected_roles', []), true)) {
            throw ValidationException::withMessages([
                'role' => "The {$role->name} role cannot be {$action}d.",
            ]);
        }
    }
}
