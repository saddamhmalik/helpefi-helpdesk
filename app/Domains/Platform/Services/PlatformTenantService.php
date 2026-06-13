<?php

namespace App\Domains\Platform\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Platform\Repositories\PlatformTenantRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Services\TenantDomainService;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PlatformTenantService
{
    public function __construct(
        private PlatformTenantRepository $tenants,
        private PlanRepository $plans,
        private PlatformTenantAdminResolver $admins,
        private PlatformAuditRecorder $audit,
    ) {}

    public function list(int $perPage = 20, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->tenants
            ->paginate($perPage, $search, $status === 'all' ? null : $status)
            ->through(fn (Tenant $tenant) => $this->presentForList($tenant));
    }

    public function stats(): array
    {
        return $this->tenants->stats();
    }

    public function presentForList(Tenant $tenant): array
    {
        return $this->present($tenant);
    }

    public function update(string $tenantId, array $data): array
    {
        $tenant = $this->tenants->find($tenantId);
        $beforeBlocked = (bool) $tenant->is_blocked;
        $beforePlan = $tenant->subscription?->plan;
        $beforeInterval = $tenant->subscription?->billing_interval;

        if (array_key_exists('is_blocked', $data)) {
            $tenant = $this->tenants->update($tenant, [
                'is_blocked' => (bool) $data['is_blocked'],
            ]);

            if ($beforeBlocked !== (bool) $tenant->is_blocked) {
                $this->audit->record(
                    $tenant->is_blocked ? 'platform.tenant.blocked' : 'platform.tenant.unblocked',
                    $tenant,
                    tenantId: $tenant->id,
                );
            }
        }

        if (array_key_exists('plan', $data) && $data['plan'] !== null && $data['plan'] !== '') {
            $this->plans->find($data['plan']);

            $interval = ($data['billing_interval'] ?? null) === 'year' ? 'year' : 'month';
            $renewsAt = $this->resolveRenewalDate($data['renews_at'] ?? null, $interval);

            $subscriptionPayload = [
                'plan' => $data['plan'],
                'status' => Subscription::STATUS_ACTIVE,
                'billing_interval' => $interval,
                'trial_ends_at' => null,
                'cancelled_at' => null,
                'access_ends_at' => null,
                'renews_at' => $renewsAt,
            ];

            $this->tenants->updateSubscription($tenant, $subscriptionPayload);
            $tenant = $this->tenants->find($tenantId);

            if ($beforePlan !== $data['plan'] || $beforeInterval !== $interval) {
                $this->audit->record(
                    'platform.tenant.plan_changed',
                    $tenant,
                    array_filter([
                        'from' => $beforePlan,
                        'to' => $data['plan'],
                        'interval' => $interval,
                        'renews_at' => $renewsAt->toIso8601String(),
                        'note' => $data['note'] ?? null,
                    ], static fn ($value) => $value !== null),
                    tenantId: $tenant->id,
                );
            }
        }

        return $this->present($tenant);
    }

    public function find(string $tenantId): Tenant
    {
        return $this->tenants->find($tenantId);
    }

    public function delete(string $tenantId): void
    {
        $tenant = $this->tenants->find($tenantId);

        $this->audit->record(
            'platform.tenant.deleted',
            $tenant,
            [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database()->getName(),
            ],
            tenantId: $tenant->id,
        );

        $tenant->delete();
    }

    private function resolveRenewalDate(?string $renewsAt, string $interval): CarbonInterface
    {
        if ($renewsAt !== null && $renewsAt !== '') {
            return Carbon::parse($renewsAt);
        }

        return $interval === 'year' ? now()->addYear() : now()->addMonth();
    }

    private function present(Tenant $tenant): array
    {
        $subscription = $tenant->subscription;
        $domainService = app(TenantDomainService::class);
        $domain = $domainService->primaryHost($tenant);
        $platformDomain = $domainService->platformHost($tenant);
        $customDomain = $tenant->domains->firstWhere('type', 'custom')?->domain;
        $admin = $this->admins->resolve($tenant);
        $planSlug = $subscription?->plan;
        $plan = $planSlug ? ($this->plans->all()[$planSlug] ?? null) : null;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'database' => $tenant->database()->getName(),
            'admin_email' => $admin['email'],
            'admin_name' => $admin['name'],
            'domain' => $domain,
            'platform_domain' => $platformDomain,
            'custom_domain' => $customDomain,
            'url' => $domainService->primaryUrl($tenant),
            'is_blocked' => (bool) $tenant->is_blocked,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'razorpay_customer' => (bool) $tenant->razorpay_customer_id,
            'subscription' => $subscription ? [
                'plan' => $planSlug,
                'plan_name' => $plan['name'] ?? ($planSlug ? ucfirst($planSlug) : null),
                'plan_price' => $plan['price'] ?? null,
                'billing_interval' => $subscription->billing_interval ?? 'month',
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
                'has_razorpay' => (bool) $subscription->razorpay_subscription_id,
            ] : null,
        ];
    }
}
