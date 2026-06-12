<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Billing\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformSubscriptionRepository
{
    public function paginate(int $perPage = 20, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        $query = Subscription::query()
            ->with(['tenant.domains'])
            ->orderByDesc('updated_at');

        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('plan', 'like', $term)
                    ->orWhere('razorpay_subscription_id', 'like', $term)
                    ->orWhere('razorpay_plan_id', 'like', $term)
                    ->orWhereHas('tenant', function ($tenantQuery) use ($term) {
                        $tenantQuery
                            ->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term);
                    });
            });
        }

        match ($status) {
            'active' => $query->where('status', Subscription::STATUS_ACTIVE)->whereNull('cancelled_at'),
            'trial' => $query->where('status', Subscription::STATUS_TRIAL)
                ->where(function ($trial) {
                    $trial->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now());
                }),
            'trial_expired' => $query->where('status', Subscription::STATUS_TRIAL)
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<=', now()),
            'cancelled' => $query->where('status', Subscription::STATUS_CANCELLED),
            'grace' => $query->whereNotNull('cancelled_at')->where('access_ends_at', '>', now()),
            'past_due' => $query->where('status', Subscription::STATUS_PAST_DUE),
            'blocked' => $query->whereHas('tenant', fn ($tenant) => $tenant->where('is_blocked', true)),
            default => null,
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function stats(): array
    {
        return [
            'total' => Subscription::query()->count(),
            'active' => Subscription::query()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->whereNull('cancelled_at')
                ->count(),
            'on_trial' => Subscription::query()
                ->where('status', Subscription::STATUS_TRIAL)
                ->where(function ($query) {
                    $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now());
                })
                ->count(),
            'cancelled' => Subscription::query()->where('status', Subscription::STATUS_CANCELLED)->count(),
            'in_grace' => Subscription::query()
                ->whereNotNull('cancelled_at')
                ->where('access_ends_at', '>', now())
                ->count(),
            'past_due' => Subscription::query()->where('status', Subscription::STATUS_PAST_DUE)->count(),
            'blocked' => Subscription::query()
                ->whereHas('tenant', fn ($tenant) => $tenant->where('is_blocked', true))
                ->count(),
        ];
    }
}
