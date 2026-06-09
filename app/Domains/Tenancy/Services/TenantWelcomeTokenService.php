<?php

namespace App\Domains\Tenancy\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TenantWelcomeTokenService
{
    public function issue(string $tenantId, string $email): string
    {
        $token = Str::random(64);

        Cache::store('central')->put(
            $this->cacheKey($token),
            [
                'tenant_id' => $tenantId,
                'email' => strtolower(trim($email)),
            ],
            now()->addMinutes(30),
        );

        return $token;
    }

    public function consume(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $payload = Cache::store('central')->pull($this->cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    private function cacheKey(string $token): string
    {
        return 'tenant_welcome:'.$token;
    }
}
