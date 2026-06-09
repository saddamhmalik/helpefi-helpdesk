<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantProvisioningService
{
    public function __construct(
        private PlanRepository $plans,
    ) {
    }

    public function provision(
        string $organizationName,
        string $slug,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
        string $plan = 'professional',
    ): Tenant {
        $slug = Str::slug($slug);

        if ($slug === '' || Tenant::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'This workspace URL is already taken.',
            ]);
        }

        $this->plans->find($plan);

        $domain = $this->tenantDomain($slug);

        if (Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $domain))->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'This workspace URL is already taken.',
            ]);
        }

        $tenant = Tenant::query()->create([
            'name' => $organizationName,
            'slug' => $slug,
            'data' => [
                'admin_name' => $adminName,
                'admin_email' => $adminEmail,
                'admin_password' => bcrypt($adminPassword),
                'plan' => $plan,
            ],
        ]);

        $tenant->domains()->create(['domain' => $domain]);

        return $tenant;
    }

    public function tenantDomain(string $slug): string
    {
        $central = config('tenancy.central_app_domain');

        return "{$slug}.{$central}";
    }

    public function tenantUrl(Tenant $tenant): string
    {
        $domain = $tenant->domains()->value('domain');
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return "{$scheme}://{$domain}";
    }

    public function welcomeUrl(Tenant $tenant, string $email): string
    {
        tenancy()->initialize($tenant);

        $tenantBaseUrl = $this->tenantUrl($tenant);
        \Illuminate\Support\Facades\URL::forceRootUrl($tenantBaseUrl);

        $welcomePath = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'welcome',
            now()->addMinutes(30),
            ['email' => $email],
            absolute: false,
        );

        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));

        tenancy()->end();

        return $tenantBaseUrl.$welcomePath;
    }

    public function createCentralSubscription(Tenant $tenant, string $plan): Subscription
    {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'status' => Subscription::STATUS_ACTIVE,
            'renews_at' => now()->addMonth(),
        ]);
    }
}
