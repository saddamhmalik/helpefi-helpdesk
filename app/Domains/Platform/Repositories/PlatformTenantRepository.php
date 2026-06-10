<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Billing\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlatformTenantRepository
{
    public function paginate(int $perPage = 20, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        $query = Tenant::query()
            ->with(['domains', 'subscription'])
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('data->admin_email', 'like', $term)
                    ->orWhere('data->admin_name', 'like', $term);
            });
        }

        match ($status) {
            'blocked' => $query->where('is_blocked', true),
            'trial' => $query->whereHas('subscription', function ($builder) {
                $builder
                    ->where('status', Subscription::STATUS_TRIAL)
                    ->where(function ($trial) {
                        $trial->whereNull('trial_ends_at')
                            ->orWhere('trial_ends_at', '>', now());
                    });
            }),
            'trial_expired' => $query->whereHas('subscription', function ($builder) {
                $builder
                    ->where('status', Subscription::STATUS_TRIAL)
                    ->whereNotNull('trial_ends_at')
                    ->where('trial_ends_at', '<=', now());
            }),
            'active' => $query
                ->where('is_blocked', false)
                ->whereHas('subscription', fn ($builder) => $builder->where('status', Subscription::STATUS_ACTIVE)),
            'no_subscription' => $query->whereDoesntHave('subscription'),
            default => null,
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function recent(int $limit = 5): Collection
    {
        return Tenant::query()
            ->with(['domains', 'subscription'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function stats(): array
    {
        $total = Tenant::query()->count();
        $blocked = Tenant::query()->where('is_blocked', true)->count();

        $onTrial = Subscription::query()
            ->where('status', Subscription::STATUS_TRIAL)
            ->where(function ($query) {
                $query->whereNull('trial_ends_at')
                    ->orWhere('trial_ends_at', '>', now());
            })
            ->count();

        $active = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->count();

        $expiredTrial = Subscription::query()
            ->where('status', Subscription::STATUS_TRIAL)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->count();

        $missingSubscription = Tenant::query()->whereDoesntHave('subscription')->count();

        return [
            'total' => $total,
            'blocked' => $blocked,
            'on_trial' => $onTrial,
            'active' => $active,
            'expired_trial' => $expiredTrial,
            'missing_subscription' => $missingSubscription,
        ];
    }

    public function allForSelect(): Collection
    {
        return Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function options(): array
    {
        return $this->allForSelect()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ])
            ->all();
    }

    public function findMany(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection();
        }

        return Tenant::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'slug']);
    }

    public function find(string $id): Tenant
    {
        return Tenant::query()
            ->with(['domains', 'subscription'])
            ->findOrFail($id);
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh(['domains', 'subscription']);
    }

    public function updateSubscription(Tenant $tenant, array $data): Subscription
    {
        $subscription = Subscription::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => null,
                'status' => Subscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(14),
                'renews_at' => null,
            ],
        );

        $subscription->update($data);

        return $subscription->fresh();
    }
}
