<?php

namespace App\Domains\Sla\Support;

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

class SlaPolicyCache
{
    public static function policyKey(string $scopeKey): string
    {
        return TenantCache::key("sla_policy.scope:{$scopeKey}");
    }

    public static function rememberPolicyId(string $scopeKey, callable $callback): ?int
    {
        if (! tenancy()->initialized) {
            return $callback();
        }

        /** @var int|null $policyId */
        $policyId = Cache::remember(self::policyKey($scopeKey), 3600, $callback);

        return $policyId;
    }

    public static function forget(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        foreach (Cache::get(TenantCache::key('sla_policy.scope_keys'), []) as $scopeKey) {
            Cache::forget(self::policyKey($scopeKey));
        }

        Cache::forget(TenantCache::key('sla_policy.scope_keys'));
    }

    public static function trackScopeKey(string $scopeKey): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $registryKey = TenantCache::key('sla_policy.scope_keys');
        $keys = Cache::get($registryKey, []);

        if (! in_array($scopeKey, $keys, true)) {
            $keys[] = $scopeKey;
            Cache::put($registryKey, $keys, 86400);
        }
    }
}
