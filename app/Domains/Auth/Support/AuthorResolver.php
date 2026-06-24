<?php

namespace App\Domains\Auth\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class AuthorResolver
{
    public static function firstUserId(): ?int
    {
        if (Role::query()->where('name', 'admin')->where('guard_name', 'web')->exists()) {
            $adminId = User::query()->role('admin')->orderBy('id')->value('id');

            if ($adminId) {
                return (int) $adminId;
            }
        }

        $userId = User::query()->orderBy('id')->value('id');

        return $userId ? (int) $userId : null;
    }
}
