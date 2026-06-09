<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Billing\Models\Subscription;

class SubscriptionRepository
{
    public function current(): Subscription
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            throw new \RuntimeException('Billing subscription requires an initialized tenant context.');
        }

        return Subscription::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'plan' => null,
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(app(\App\Domains\Tenancy\Services\CentralSettingsService::class)->trialDays()),
                'renews_at' => null,
            ],
        );
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription->fresh();
    }
}
