<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformRole;
use App\Domains\Platform\Repositories\PlatformRoleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformRoleService
{
    public function __construct(private PlatformRoleRepository $roles)
    {
    }

    public function catalog(): array
    {
        return collect(config('platform_permissions.groups', []))
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

    public function show(int $id): PlatformRole
    {
        return $this->roles->find($id);
    }

    public function create(string $name, array $permissions): PlatformRole
    {
        $normalized = $this->normalizeName($name);
        $this->assertUniqueName($normalized);
        $this->assertValidPermissions($permissions);

        $role = $this->roles->create($normalized);

        return $this->roles->syncPermissions($role, $permissions);
    }

    public function update(int $id, ?string $name, array $permissions): PlatformRole
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

        return $this->roles->syncPermissions($role, $permissions);
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

        $this->roles->delete($role);
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

    private function assertNotProtected(PlatformRole $role, string $action): void
    {
        if (in_array($role->name, config('platform_permissions.protected_roles', []), true)) {
            throw ValidationException::withMessages([
                'role' => "The {$role->name} role cannot be {$action}d.",
            ]);
        }
    }
}
