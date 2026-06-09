<?php

namespace App\Domains\Platform\Services;

use App\Models\PlatformUser;

class PlatformAuthorizationService
{
    public function allows(PlatformUser $user, string $permission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('super_admin') || $this->isUnassignedBootstrapAdmin($user)) {
            return true;
        }

        return $user->hasPermission($permission);
    }

    private function isUnassignedBootstrapAdmin(PlatformUser $user): bool
    {
        if ($user->roles()->exists()) {
            return false;
        }

        return PlatformUser::query()->count() === 1;
    }

    public function allowsAny(PlatformUser $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }
}
