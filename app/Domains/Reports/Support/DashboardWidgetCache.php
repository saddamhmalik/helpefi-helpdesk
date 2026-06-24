<?php

namespace App\Domains\Reports\Support;

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class DashboardWidgetCache
{
    public static function key(): string
    {
        return TenantCache::key('dashboard_widgets');
    }

    public static function remember(int $ttl, callable $callback): mixed
    {
        if (! tenancy()->initialized) {
            return $callback();
        }

        $key = self::key();
        $value = Cache::get($key);

        if ($value !== null) {
            return $value;
        }

        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $ttl, $callback) {
            return Cache::remember($key, $ttl + random_int(0, 30), $callback);
        });
    }

    public static function forget(): void
    {
        if (tenancy()->initialized) {
            Cache::forget(self::key());
        }
    }
}
