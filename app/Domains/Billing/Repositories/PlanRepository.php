<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Billing\Models\Subscription;
use InvalidArgumentException;

class PlanRepository
{
    public function all(): array
    {
        return config('plans', []);
    }

    public function find(string $slug): array
    {
        $plan = $this->all()[$slug] ?? null;

        if (! $plan) {
            throw new InvalidArgumentException("Unknown plan: {$slug}");
        }

        return array_merge($plan, ['slug' => $slug]);
    }

    public function slugs(): array
    {
        return array_keys($this->all());
    }
}
