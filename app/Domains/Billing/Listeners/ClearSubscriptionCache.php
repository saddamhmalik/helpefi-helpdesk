<?php

namespace App\Domains\Billing\Listeners;

use App\Domains\Billing\Repositories\SubscriptionRepository;
use Stancl\Tenancy\Events\InitializingTenancy;

class ClearSubscriptionCache
{
    public function __construct(private SubscriptionRepository $subscriptions)
    {
    }

    public function handle(InitializingTenancy $event): void
    {
        $this->subscriptions->forget();
    }
}
