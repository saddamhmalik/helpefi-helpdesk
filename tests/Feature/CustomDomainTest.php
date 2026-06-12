<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Tenancy\Support\DomainDnsVerifier;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class CustomDomainTest extends TenantTestCase
{
    use RefreshDatabase;

    private function setPlan(string $plan): void
    {
        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => $plan,
                'status' => Subscription::STATUS_ACTIVE,
                'renews_at' => now()->addMonth(),
            ],
        );
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    public function test_enterprise_admin_can_view_custom_domain_settings(): void
    {
        $this->setPlan('enterprise');

        $this->actingAs($this->admin())
            ->tenantGet('/settings/custom-domain')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/CustomDomain')
                ->has('customDomain')
                ->where('customDomain.can_manage', true));
    }

    public function test_starter_plan_can_view_but_not_add_custom_domain(): void
    {
        $this->setPlan('starter');

        $this->actingAs($this->admin())
            ->tenantGet('/settings/custom-domain')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('customDomain.can_manage', false));

        $this->actingAs($this->admin())
            ->tenantPost('/settings/custom-domain', [
                'domain' => 'support.anytrip.com',
            ])
            ->assertForbidden();
    }

    public function test_enterprise_admin_can_request_custom_domain(): void
    {
        $this->setPlan('enterprise');

        $this->actingAs($this->admin())
            ->tenantPost('/settings/custom-domain', [
                'domain' => 'support.anytrip.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $this->tenant->id,
            'domain' => 'support.anytrip.com',
            'type' => TenantDomain::TYPE_CUSTOM,
            'verification_status' => TenantDomain::STATUS_PENDING,
        ], 'central');
    }

    public function test_enterprise_admin_can_verify_custom_domain(): void
    {
        $this->setPlan('enterprise');

        $this->mock(DomainDnsVerifier::class, function ($mock) {
            $mock->shouldReceive('hasTxtRecord')->andReturn(true);
        });

        TenantDomain::query()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'support.anytrip.com',
            'type' => TenantDomain::TYPE_CUSTOM,
            'is_primary' => false,
            'verification_status' => TenantDomain::STATUS_PENDING,
            'verification_token' => 'helpdesk-verify=test-token',
        ]);

        $this->actingAs($this->admin())
            ->tenantPost('/settings/custom-domain/verify')
            ->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $this->tenant->id,
            'domain' => 'support.anytrip.com',
            'verification_status' => TenantDomain::STATUS_VERIFIED,
            'is_primary' => true,
        ], 'central');
    }

    public function test_platform_subdomain_redirects_to_custom_domain_when_enabled(): void
    {
        $this->setPlan('enterprise');

        $platformHost = $this->tenant->domains()->value('domain');

        TenantDomain::query()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'support.anytrip.com',
            'type' => TenantDomain::TYPE_CUSTOM,
            'is_primary' => true,
            'verification_status' => TenantDomain::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        TenantDomain::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('type', TenantDomain::TYPE_PLATFORM)
            ->update(['is_primary' => false]);

        $this->tenant->update(['custom_domain_redirect' => true]);

        $this->get('http://'.$platformHost.'/login')
            ->assertRedirect('http://support.anytrip.com/login');
    }

    public function test_removing_custom_domain_stops_platform_redirect(): void
    {
        $this->setPlan('enterprise');

        $platformHost = $this->tenant->domains()->value('domain');

        TenantDomain::query()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'support.anytrip.com',
            'type' => TenantDomain::TYPE_CUSTOM,
            'is_primary' => true,
            'verification_status' => TenantDomain::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        TenantDomain::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('type', TenantDomain::TYPE_PLATFORM)
            ->update(['is_primary' => false]);

        $this->tenant->update(['custom_domain_redirect' => true]);

        $this->actingAs($this->admin())
            ->tenantDelete('/settings/custom-domain')
            ->assertRedirect();

        $this->tenant->refresh();

        $this->assertFalse($this->tenant->custom_domain_redirect);
        $this->assertDatabaseMissing('domains', [
            'tenant_id' => $this->tenant->id,
            'type' => TenantDomain::TYPE_CUSTOM,
        ], 'central');
        $this->assertDatabaseHas('domains', [
            'tenant_id' => $this->tenant->id,
            'type' => TenantDomain::TYPE_PLATFORM,
            'is_primary' => true,
        ], 'central');

        $this->get('http://'.$platformHost.'/login')->assertOk();
    }
}
