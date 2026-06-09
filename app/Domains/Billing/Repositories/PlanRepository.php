<?php

namespace App\Domains\Billing\Repositories;

use App\Domains\Tenancy\Services\CentralSettingsService;
use InvalidArgumentException;

class PlanRepository
{
    public function __construct(private CentralSettingsService $centralSettings)
    {
    }

    public function all(): array
    {
        return $this->centralSettings->planCatalog();
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
