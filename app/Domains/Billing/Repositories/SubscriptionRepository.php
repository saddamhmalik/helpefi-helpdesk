<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Billing\Models\Subscription;

class SubscriptionRepository
{
    public function current(): Subscription
    {
        return Subscription::query()->firstOrCreate([], [
            'plan' => config('billing.default_plan', 'professional'),
            'status' => Subscription::STATUS_ACTIVE,
            'renews_at' => now()->addMonth(),
        ]);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription->fresh();
    }
}
