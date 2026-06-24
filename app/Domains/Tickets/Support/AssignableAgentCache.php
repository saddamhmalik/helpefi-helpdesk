<?php

namespace App\Domains\Tickets\Support;

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class AssignableAgentCache
{
    public static function key(): string
    {
        return TenantCache::key('assignable_agent_ids');
    }

    public static function remember(callable $callback): array
    {
        if (! tenancy()->initialized) {
            return $callback();
        }

        return Cache::remember(self::key(), 300, $callback);
    }

    public static function forget(): void
    {
        if (tenancy()->initialized) {
            Cache::forget(self::key());
        }
    }
}
