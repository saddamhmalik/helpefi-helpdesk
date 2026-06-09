<?php

namespace Database\Seeders;

use App\Domains\Platform\Models\PlatformPermission;
use App\Domains\Platform\Models\PlatformRole;
use App\Models\PlatformUser;
use Illuminate\Database\Seeder;

class PlatformPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->permissionNames() as $name) {
            PlatformPermission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'platform'],
                ['guard_name' => 'platform'],
            );
        }

        $all = PlatformPermission::query()->pluck('name')->all();

        foreach (config('platform_permissions.default_role_permissions', []) as $roleName => $permissions) {
            $role = PlatformRole::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'platform'],
                ['guard_name' => 'platform'],
            );

            if ($permissions === '*') {
                $role->permissions()->sync(
                    PlatformPermission::query()->pluck('id')->all(),
                );

                continue;
            }

            $permissionIds = PlatformPermission::query()
                ->whereIn('name', $permissions)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        $superAdminRoleId = PlatformRole::query()->where('name', 'super_admin')->value('id');

        if (! $superAdminRoleId) {
            return;
        }

        PlatformUser::query()->each(function (PlatformUser $user) use ($superAdminRoleId): void {
            if ($user->roles()->exists()) {
                return;
            }

            $user->roles()->sync([$superAdminRoleId]);
        });
    }

    private function permissionNames(): array
    {
        $names = [];

        foreach (config('platform_permissions.groups', []) as $permissions) {
            foreach ($permissions as $name => $label) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
