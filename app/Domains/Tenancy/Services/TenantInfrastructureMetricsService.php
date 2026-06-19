<?php

namespace App\Domains\Tenancy\Services;

use Illuminate\Support\Facades\Cache;

class TenantInfrastructureMetricsService
{
    private const VERIFY_FAILURES_KEY = 'metrics:tenant_infrastructure:verify_failures_total';

    private const HEALTH_FAILURES_KEY = 'metrics:tenant_infrastructure:health_failures_total';

    public function incrementVerifyFailures(): void
    {
        $this->increment(self::VERIFY_FAILURES_KEY);
    }

    public function incrementHealthFailures(): void
    {
        $this->increment(self::HEALTH_FAILURES_KEY);
    }

    public function totals(): array
    {
        return [
            'verify_failures_total' => (int) Cache::get(self::VERIFY_FAILURES_KEY, 0),
            'health_failures_total' => (int) Cache::get(self::HEALTH_FAILURES_KEY, 0),
        ];
    }

    private function increment(string $key): void
    {
        if (! Cache::has($key)) {
            Cache::put($key, 0);
        }

        Cache::increment($key);
    }
}
