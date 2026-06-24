<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Billing\Models\Subscription;

class SubscriptionRepository
{
    private ?Subscription $current = null;

    public function current(): Subscription
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            throw new \RuntimeException('Billing subscription requires an initialized tenant context.');
        }

        if ($this->current !== null && $this->current->tenant_id === $tenantId) {
            return $this->current;
        }

        return $this->current = Subscription::query()->firstOrCreate(
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
        $this->current = null;

        return $subscription->fresh();
    }

    public function forget(): void
    {
        $this->current = null;
    }

    public function countByPlan(string $slug): int
    {
        return Subscription::query()
            ->where('plan', $slug)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->count();
    }
}
