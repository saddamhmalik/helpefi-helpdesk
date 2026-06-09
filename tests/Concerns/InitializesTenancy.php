<?php

namespace Tests\Concerns;

use App\Domains\Tenancy\Services\TenantProvisioningService;
use App\Models\Tenant;

trait InitializesTenancy
{
    protected Tenant $tenant;

    protected function provisionTenancy(string $slug = 'test'): void
    {
        $existing = Tenant::query()->where('slug', $slug)->first();

        if ($existing) {
            $this->tenant = $existing;

            return;
        }

        $this->tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Test Org',
            slug: $slug,
            adminName: 'Admin User',
            adminEmail: 'admin@helpdesk.test',
            adminPassword: 'password',
            plan: config('billing.default_plan', 'enterprise'),
        );
    }

    protected function runInTenant(callable $callback): mixed
    {
        tenancy()->initialize($this->tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }
}
