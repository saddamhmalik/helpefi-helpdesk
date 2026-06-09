<?php

namespace App\Support;

class TenantCacheKey
{
    public static function scoped(string $key): string
    {
        $tenantId = tenant('id');

        return $tenantId ? "{$tenantId}:{$key}" : $key;
    }
}
