<?php

namespace App\Domains\Tenancy\Support;

class CentralDomain
{
    public static function apex(): string
    {
        return strtolower((string) config('tenancy.central_app_domain'));
    }

    public static function www(): string
    {
        $apex = self::apex();

        return $apex !== '' ? 'www.'.$apex : '';
    }

    public static function isCentralHost(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $host = strtolower($host);
        $apex = self::apex();

        if ($apex !== '' && ($host === $apex || $host === 'www.'.$apex)) {
            return true;
        }

        foreach (config('tenancy.central_domains', []) as $configured) {
            if (is_string($configured) && strtolower($configured) === $host) {
                return true;
            }
        }

        return false;
    }
}
