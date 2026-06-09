<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Repositories\TenantDomainRepository;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantProvisioningService
{
    public function provision(
        string $organizationName,
        string $slug,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
    ): Tenant {
        $slug = Str::slug($slug);

        if ($slug === '' || Tenant::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'This workspace URL is already taken.',
            ]);
        }

        $domain = $this->tenantDomain($slug);

        if (Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $domain))->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'This workspace URL is already taken.',
            ]);
        }

        $tenant = Tenant::query()->create([
            'name' => $organizationName,
            'slug' => $slug,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
        ]);

        app(TenantDomainRepository::class)->createPlatform($tenant, $domain);

        return $tenant;
    }

    public function tenantDomain(string $slug): string
    {
        $central = config('tenancy.central_app_domain');

        return "{$slug}.{$central}";
    }

    public function tenantUrl(Tenant $tenant): string
    {
        return app(TenantDomainService::class)->primaryUrl($tenant)
            ?? 'http://'.app(TenantDomainRepository::class)->platformDomain($tenant)?->domain;
    }

    public function welcomeUrl(Tenant $tenant, string $email): string
    {
        $token = app(TenantWelcomeTokenService::class)->issue($tenant->id, $email);

        return $this->tenantUrl($tenant).'/welcome?token='.urlencode($token);
    }

    public function createCentralSubscription(Tenant $tenant): Subscription
    {
        $trialDays = app(CentralSettingsService::class)->trialDays();

        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan' => null,
            'status' => Subscription::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays($trialDays),
            'renews_at' => null,
        ]);
    }
}
