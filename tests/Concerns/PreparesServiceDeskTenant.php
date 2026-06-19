<?php

namespace Tests\Concerns;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Services\TenantSetupService;

trait PreparesServiceDeskTenant
{
    protected function prepareServiceDeskTenant(string $plan = 'professional', array $activeAddons = ['service_desk']): void
    {
        app(TenantSetupService::class)->finish();

        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => $plan,
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addMonth(),
                'renews_at' => null,
                'active_addons' => $activeAddons,
            ],
        );
    }
}
