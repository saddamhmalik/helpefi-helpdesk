<?php

namespace App\Domains\Platform\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Services\SubscriptionLifecycleService;
use App\Domains\Platform\Repositories\PlatformSubscriptionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformSubscriptionService
{
    public function __construct(
        private PlatformSubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private PlatformTenantAdminResolver $admins,
        private SubscriptionLifecycleService $lifecycle,
    ) {
    }

    public function list(int $perPage = 20, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->subscriptions
            ->paginate($perPage, $search, $status === 'all' ? null : $status)
            ->through(fn (Subscription $subscription) => $this->present($subscription));
    }

    public function stats(): array
    {
        return $this->subscriptions->stats();
    }

    private function present(Subscription $subscription): array
    {
        $tenant = $subscription->tenant;
        $domain = $tenant?->domains->first()?->domain;
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $planSlug = $subscription->plan;
        $plan = $planSlug ? ($this->plans->all()[$planSlug] ?? null) : null;
        $admin = $tenant ? $this->admins->resolve($tenant) : ['name' => null, 'email' => null];

        return [
            'id' => $subscription->id,
            'plan' => $planSlug,
            'plan_name' => $plan['name'] ?? ($planSlug ? ucfirst($planSlug) : 'No plan'),
            'plan_price' => $plan['price'] ?? null,
            'status' => $subscription->status,
            'on_trial' => $subscription->isOnTrial(),
            'trial_expired' => $subscription->isTrialExpired(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'renews_at' => $subscription->renews_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'access_ends_at' => $subscription->access_ends_at?->toIso8601String(),
            'in_grace_period' => $subscription->isInGracePeriod(),
            'grace_days_remaining' => $subscription->graceDaysRemaining(),
            'cancellation_pending' => $subscription->cancelled_at !== null && $subscription->isActive(),
            'cancellation_grace_days' => $this->lifecycle->graceDays(),
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            'stripe_price_id' => $subscription->stripe_price_id,
            'has_stripe' => (bool) $subscription->stripe_subscription_id,
            'updated_at' => $subscription->updated_at?->toIso8601String(),
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'admin_name' => $admin['name'],
                'admin_email' => $admin['email'],
                'domain' => $domain,
                'url' => $domain ? "{$scheme}://{$domain}" : null,
                'is_blocked' => (bool) $tenant->is_blocked,
                'stripe_customer' => (bool) $tenant->stripe_id,
            ] : null,
        ];
    }
}
