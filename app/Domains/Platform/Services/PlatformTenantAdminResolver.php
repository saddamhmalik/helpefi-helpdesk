<?php

namespace App\Domains\Platform\Services;

use App\Models\Tenant;
use App\Models\User;

class PlatformTenantAdminResolver
{
    public function resolve(Tenant $tenant): array
    {
        if (filled($tenant->admin_email)) {
            return [
                'name' => $tenant->admin_name,
                'email' => $tenant->admin_email,
            ];
        }

        return $tenant->run(function () {
            $user = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                ->orderBy('id')
                ->first(['name', 'email'])
                ?? User::query()->orderBy('id')->first(['name', 'email']);

            return [
                'name' => $user?->name,
                'email' => $user?->email,
            ];
        });
    }
}
