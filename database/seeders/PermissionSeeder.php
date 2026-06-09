<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $all = Permission::query()->where('guard_name', 'web')->pluck('name')->all();

        foreach (config('permissions.default_role_permissions', []) as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($permissions === '*') {
                $role->syncPermissions($all);

                continue;
            }

            $role->syncPermissions($permissions);
        }
    }

    private function permissionNames(): array
    {
        $names = [];

        foreach (config('permissions.groups', []) as $permissions) {
            foreach ($permissions as $name => $label) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
