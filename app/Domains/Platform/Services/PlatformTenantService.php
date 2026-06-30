<?php

namespace App\Domains\Platform\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Platform\Repositories\PlatformTenantRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Services\CentralSettingsService;
use App\Domains\Tenancy\Services\TenantByoEligibilityService;
use App\Domains\Tenancy\Services\TenantDomainService;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use App\Domains\Tenancy\Support\TenantInfrastructurePresenter;
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
        private PlatformTenantReminderService $reminders,
        private PlatformAuditRecorder $audit,
        private CentralSettingsService $centralSettings,
        private TenantByoEligibilityService $byoEligibility,
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
        $beforeByoAllowed = (bool) $tenant->byo_allowed;
        $beforePlan = $tenant->subscription?->plan;
        $beforeInterval = $tenant->subscription?->billing_interval;
        $beforeRenewsAt = $tenant->subscription?->renews_at;
        $beforeCustomAmount = $tenant->subscription?->custom_amount;
        $beforeAddons = $tenant->subscription?->active_addons ?? [];
        $beforeCurrency = $tenant->subscription?->currency;

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

        if (array_key_exists('byo_allowed', $data)) {
            $tenant = $this->tenants->update($tenant, [
                'byo_allowed' => (bool) $data['byo_allowed'],
            ]);

            if ($beforeByoAllowed !== (bool) $tenant->byo_allowed) {
                $this->audit->record(
                    $tenant->byo_allowed ? 'platform.tenant.byo_allowed' : 'platform.tenant.byo_disallowed',
                    $tenant,
                    tenantId: $tenant->id,
                );
            }
        }

        if (array_key_exists('plan', $data) && $data['plan'] !== null && $data['plan'] !== '') {
            $this->plans->find($data['plan']);

            $interval = ($data['billing_interval'] ?? null) === 'year' ? 'year' : 'month';
            $planChanged = $beforePlan !== $data['plan'] || $beforeInterval !== $interval;
            $renewsAt = $this->resolveRenewalDate($data['renews_at'] ?? null, $interval, $planChanged, $beforeRenewsAt);

            $customPriceProvided = array_key_exists('custom_price', $data);
            $customAmount = $customPriceProvided
                ? $this->resolveCustomAmount($data['custom_price'])
                : $beforeCustomAmount;

            $billingCurrency = array_key_exists('billing_currency', $data)
                ? $this->resolveBillingCurrency($data['billing_currency'])
                : $beforeCurrency;

            $activeAddons = array_key_exists('addons', $data)
                ? $this->resolveActiveAddons($data['addons'] ?? [], $data['plan'])
                : $beforeAddons;

            $subscriptionPayload = [
                'plan' => $data['plan'],
                'status' => Subscription::STATUS_ACTIVE,
                'billing_interval' => $interval,
                'trial_ends_at' => null,
                'cancelled_at' => null,
                'access_ends_at' => null,
                'renews_at' => $renewsAt,
                'active_addons' => $activeAddons,
            ];

            if (array_key_exists('billing_currency', $data)) {
                $subscriptionPayload['currency'] = $billingCurrency;
            }

            if ($customPriceProvided) {
                $subscriptionPayload['custom_amount'] = $customAmount;
                $subscriptionPayload['currency'] = $billingCurrency ?? $this->centralSettings->currency();
            }

            $this->tenants->updateSubscription($tenant, $subscriptionPayload);
            $tenant = $this->tenants->find($tenantId);

            $renewalChanged = $beforeRenewsAt === null || ! $beforeRenewsAt->equalTo($renewsAt);
            $noteProvided = isset($data['note']) && $data['note'] !== '';
            $customAmountChanged = $customPriceProvided && $customAmount !== $beforeCustomAmount;
            $addonsChanged = array_key_exists('addons', $data)
                && $activeAddons !== $beforeAddons;
            $currencyChanged = array_key_exists('billing_currency', $data)
                && $billingCurrency !== $beforeCurrency;

            if ($planChanged || $renewalChanged || $noteProvided || $customAmountChanged || $addonsChanged || $currencyChanged) {
                $this->audit->record(
                    'platform.tenant.plan_changed',
                    $tenant,
                    array_filter([
                        'from' => $beforePlan,
                        'to' => $data['plan'],
                        'interval' => $interval,
                        'renews_at' => $renewsAt->toIso8601String(),
                        'custom_amount' => $customPriceProvided ? $customAmount : null,
                        'currency' => array_key_exists('billing_currency', $data) ? $billingCurrency : null,
                        'addons' => array_key_exists('addons', $data) ? $activeAddons : null,
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

    private function resolveCustomAmount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function resolveRenewalDate(?string $renewsAt, string $interval, bool $planChanged, ?CarbonInterface $existing): CarbonInterface
    {
        if ($renewsAt !== null && $renewsAt !== '') {
            return Carbon::parse($renewsAt);
        }

        if (! $planChanged && $existing !== null) {
            return $existing;
        }

        return $interval === 'year' ? now()->addYear() : now()->addMonth();
    }

    private function resolveBillingCurrency(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtoupper((string) $value);
        $base = $this->centralSettings->currency();

        if ($normalized === $base) {
            return $base;
        }

        if ($this->centralSettings->indiaPricingEnabled()
            && $normalized === $this->centralSettings->indiaCurrency()) {
            return $normalized;
        }

        return null;
    }

    private function resolveActiveAddons(array $addonKeys, string $planSlug): array
    {
        $plan = $this->plans->all()[$planSlug] ?? [];
        $planFeatures = $plan['features'] ?? [];
        $catalog = $this->centralSettings->addonCatalog();

        return collect($addonKeys)
            ->filter(fn (string $key) => isset($catalog[$key]) && ($catalog[$key]['enabled'] ?? true))
            ->reject(function (string $key) use ($planFeatures, $catalog) {
                $feature = $catalog[$key]['feature'] ?? null;

                return $feature && in_array($feature, $planFeatures, true);
            })
            ->unique()
            ->values()
            ->all();
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
        $interval = $subscription?->billing_interval ?? 'month';
        $india = $subscription?->currency
            && strtoupper((string) $subscription->currency) === $this->centralSettings->indiaCurrency();
        $activeAddonKeys = $subscription?->active_addons ?? [];
        $addonCatalog = $this->centralSettings->addonCatalog();

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
            'byo_allowed' => (bool) $tenant->byo_allowed,
            'byo_eligible' => $this->byoEligibility->isEligible($tenant),
            'created_at' => $tenant->created_at?->toIso8601String(),
            'razorpay_customer' => (bool) $tenant->razorpay_customer_id,
            'infrastructure' => TenantInfrastructurePresenter::summary($tenant->infrastructure),
            'subscription' => $subscription ? [
                'plan' => $planSlug,
                'plan_name' => $plan['name'] ?? ($planSlug ? ucfirst($planSlug) : null),
                'plan_price' => $plan ? PlanCatalogDefinition::priceForInterval($plan, $interval, $india) : null,
                'custom_amount' => $subscription->custom_amount,
                'currency' => $subscription->currency,
                'billing_interval' => $subscription->billing_interval ?? 'month',
                'active_addons' => $activeAddonKeys,
                'addon_names' => collect($activeAddonKeys)
                    ->map(fn (string $key) => $addonCatalog[$key]['name'] ?? $key)
                    ->values()
                    ->all(),
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
            'lifecycle_emails' => $this->reminders->statusForTenant($tenant),
        ];
    }
}
