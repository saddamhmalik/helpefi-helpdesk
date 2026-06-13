<?php

namespace Tests\Feature;

use App\Domains\Billing\Services\RazorpayAddonSyncService;
use App\Domains\Billing\Services\RazorpayPlanSyncService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CentralSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'razorpay.enabled' => false,
            'razorpay.secret' => null,
        ]);

        $this->seed(PlatformUserSeeder::class);
    }

    private function adminLogin(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_admin_settings_update_syncs_plans_with_razorpay_when_enabled(): void
    {
        config([
            'razorpay.enabled' => true,
            'razorpay.secret' => 'secret_test_example',
        ]);

        $this->mock(RazorpayPlanSyncService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('skipped')->andReturn([]);
            $mock->shouldReceive('syncCatalog')
                ->once()
                ->andReturnUsing(fn (array $catalog) => collect($catalog)
                    ->map(fn (array $plan, string $slug) => array_merge($plan, [
                        'razorpay_plan_id' => "plan_monthly_{$slug}",
                        'razorpay_plan_id_monthly' => "plan_monthly_{$slug}",
                        'razorpay_plan_id_yearly' => "plan_yearly_{$slug}",
                    ]))
                    ->all());
        });

        $this->mock(RazorpayAddonSyncService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('skipped')->andReturn([]);
            $mock->shouldReceive('syncCatalog')->andReturnUsing(fn (array $catalog) => $catalog);
        });

        $this->adminLogin();

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/settings', [
            'trial_days' => 14,
            'tenant_purge_grace_days' => 15,
            'tenant_purge_enabled' => true,
            'currency' => 'INR',
            'plans' => [
                [
                    'slug' => 'starter',
                    'name' => 'Starter',
                    'price' => 29,
                    'price_yearly' => 290,
                    'limits' => ['agents' => 3, 'tickets_monthly' => 50],
                    'features' => [],
                ],
                [
                    'slug' => 'professional',
                    'name' => 'Professional',
                    'price' => 79,
                    'price_yearly' => 790,
                    'limits' => ['agents' => 15, 'tickets_monthly' => 500],
                    'features' => ['automation'],
                ],
                [
                    'slug' => 'enterprise',
                    'name' => 'Enterprise',
                    'price' => 199,
                    'price_yearly' => 1990,
                    'limits' => ['agents' => null, 'tickets_monthly' => null],
                    'features' => ['automation', 'ai'],
                ],
            ],
        ])->assertRedirect();

        $starter = app(\App\Domains\Billing\Repositories\PlanRepository::class)->find('starter');

        $this->assertSame('plan_monthly_starter', $starter['razorpay_plan_id']);
        $this->assertSame('plan_monthly_starter', $starter['razorpay_plan_id_monthly']);
        $this->assertSame('plan_yearly_starter', $starter['razorpay_plan_id_yearly']);
    }

    public function test_admin_settings_update_persists_price_change_when_existing_catalog_has_price_monthly(): void
    {
        \App\Domains\Tenancy\Models\CentralSetting::query()->update([
            'plan_catalog' => [
                'starter' => [
                    'name' => 'Starter',
                    'price' => 29,
                    'price_monthly' => 29,
                    'price_yearly' => 4999,
                    'razorpay_plan_id' => 'plan_existing_monthly',
                    'razorpay_plan_id_monthly' => 'plan_existing_monthly',
                    'razorpay_plan_id_yearly' => 'plan_existing_yearly',
                    'limits' => ['agents' => 1, 'tickets_monthly' => 50],
                    'features' => [],
                ],
                'professional' => [
                    'name' => 'Professional',
                    'price' => 79,
                    'price_monthly' => 79,
                    'price_yearly' => 6999,
                    'limits' => ['agents' => 10, 'tickets_monthly' => 500],
                    'features' => ['automation'],
                ],
                'enterprise' => [
                    'name' => 'Enterprise',
                    'price' => 199,
                    'price_monthly' => 199,
                    'price_yearly' => 9999,
                    'limits' => ['agents' => null, 'tickets_monthly' => null],
                    'features' => ['ai'],
                ],
            ],
        ]);

        $this->mock(RazorpayPlanSyncService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('skipped')->andReturn([]);
            $mock->shouldReceive('syncCatalog')
                ->once()
                ->withArgs(function (array $catalog): bool {
                    return ($catalog['starter']['price_monthly'] ?? null) === 499;
                })
                ->andReturnUsing(fn (array $catalog) => collect($catalog)
                    ->map(fn (array $plan, string $slug) => array_merge($plan, [
                        'razorpay_plan_id' => $slug === 'starter' ? 'plan_new_monthly' : ($plan['razorpay_plan_id_monthly'] ?? null),
                        'razorpay_plan_id_monthly' => $slug === 'starter' ? 'plan_new_monthly' : ($plan['razorpay_plan_id_monthly'] ?? null),
                        'razorpay_plan_id_yearly' => $plan['razorpay_plan_id_yearly'] ?? null,
                    ]))
                    ->all());
        });

        $this->mock(RazorpayAddonSyncService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('skipped')->andReturn([]);
            $mock->shouldReceive('syncCatalog')->andReturnUsing(fn (array $catalog) => $catalog);
        });

        config([
            'razorpay.enabled' => true,
            'razorpay.secret' => 'secret_test_example',
        ]);

        $this->adminLogin();

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/settings', [
            'trial_days' => 14,
            'tenant_purge_grace_days' => 15,
            'tenant_purge_enabled' => true,
            'currency' => 'INR',
            'plans' => [
                [
                    'slug' => 'starter',
                    'name' => 'Starter',
                    'price' => 499,
                    'price_yearly' => 4999,
                    'limits' => ['agents' => 1, 'tickets_monthly' => 50],
                    'features' => [],
                ],
                [
                    'slug' => 'professional',
                    'name' => 'Professional',
                    'price' => 79,
                    'price_yearly' => 6999,
                    'limits' => ['agents' => 10, 'tickets_monthly' => 500],
                    'features' => ['automation'],
                ],
                [
                    'slug' => 'enterprise',
                    'name' => 'Enterprise',
                    'price' => 199,
                    'price_yearly' => 9999,
                    'limits' => ['agents' => null, 'tickets_monthly' => null],
                    'features' => ['ai'],
                ],
            ],
        ])->assertRedirect();

        $starter = app(\App\Domains\Billing\Repositories\PlanRepository::class)->find('starter');

        $this->assertSame(499, $starter['price_monthly']);
        $this->assertSame('plan_new_monthly', $starter['razorpay_plan_id_monthly']);
    }

    public function test_admin_can_update_trial_days_pricing_and_currency(): void
    {
        $this->adminLogin();

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/settings', [
            'trial_days' => 21,
            'tenant_purge_grace_days' => 10,
            'tenant_purge_enabled' => false,
            'currency' => 'EUR',
            'plans' => [
                [
                    'slug' => 'starter',
                    'name' => 'Starter',
                    'price' => 35,
                    'price_yearly' => 350,
                    'limits' => ['agents' => 5, 'tickets_monthly' => 100],
                    'features' => [],
                ],
                [
                    'slug' => 'professional',
                    'name' => 'Pro',
                    'price' => 99,
                    'price_yearly' => 990,
                    'limits' => ['agents' => 20, 'tickets_monthly' => 1000],
                    'features' => ['automation', 'sla', 'channels'],
                ],
                [
                    'slug' => 'enterprise',
                    'name' => 'Enterprise',
                    'price' => 249,
                    'price_yearly' => 2490,
                    'limits' => ['agents' => null, 'tickets_monthly' => null],
                    'features' => ['automation', 'service_catalog', 'channels', 'sla', 'workspace', 'ai', 'integrations', 'assets'],
                ],
            ],
            'addons' => [
                [
                    'key' => 'service_desk',
                    'name' => 'Service Desk (ITSM)',
                    'description' => 'ITIL workflows and war rooms.',
                    'price_monthly' => 59,
                    'enabled' => true,
                ],
            ],
        ])->assertRedirect();

        $settings = app(\App\Domains\Tenancy\Services\CentralSettingsService::class);
        $starter = app(\App\Domains\Billing\Repositories\PlanRepository::class)->find('starter');
        $professional = app(\App\Domains\Billing\Repositories\PlanRepository::class)->find('professional');

        $this->assertSame(21, $settings->trialDays());
        $this->assertSame('EUR', $settings->currency());
        $this->assertSame(5, $starter['limits']['agents']);
        $this->assertSame(100, $starter['limits']['tickets_monthly']);
        $this->assertSame([], $starter['features']);
        $this->assertSame(99, $professional['price']);
        $this->assertContains('automation', $professional['features']);
        $this->assertNotContains('ai', $professional['features']);
    }

    public function test_homepage_uses_configured_pricing_and_currency(): void
    {
        \App\Domains\Tenancy\Models\CentralSetting::query()->update([
            'currency' => 'GBP',
            'plan_catalog' => [
                'starter' => [
                    'name' => 'Starter',
                    'price' => 40,
                    'limits' => ['agents' => 3, 'tickets_monthly' => 50],
                    'features' => [],
                ],
                'professional' => [
                    'name' => 'Growth',
                    'price' => 120,
                    'limits' => ['agents' => 15, 'tickets_monthly' => 500],
                    'features' => ['automation', 'sla'],
                ],
                'enterprise' => [
                    'name' => 'Scale',
                    'price' => 300,
                    'limits' => ['agents' => null, 'tickets_monthly' => null],
                    'features' => ['automation', 'ai', 'integrations', 'assets'],
                ],
            ],
        ]);

        $this->get('http://'.config('tenancy.central_app_domain').'/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('plans', fn ($plans) => collect($plans)->firstWhere('slug', 'professional')['price_monthly'] === 120
                    && collect($plans)->firstWhere('slug', 'professional')['price_yearly'] === 1200)
                ->where('currency.code', 'GBP')
                ->where('currency.symbol', '£')
            );
    }

    public function test_new_subscriptions_use_configured_trial_days(): void
    {
        \App\Domains\Tenancy\Models\CentralSetting::query()->update(['trial_days' => 7]);

        $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Trial Length Co',
            'slug' => 'trial-length',
            'name' => 'Jane Admin',
            'email' => 'jane@trial.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(409);

        $tenant = Tenant::query()->where('slug', 'trial-length')->firstOrFail();
        $subscription = \App\Domains\Billing\Models\Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(\App\Domains\Billing\Models\Subscription::STATUS_TRIAL, $subscription->status);
        $this->assertNull($subscription->plan);
        $this->assertTrue($subscription->trial_ends_at->isAfter(now()->addDays(6)));
        $this->assertTrue($subscription->trial_ends_at->isBefore(now()->addDays(8)));
    }

    public function test_registration_creates_tenant_admin_with_same_email(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/register', [
            'organization_name' => 'Same Email Co',
            'slug' => 'same-email-co',
            'name' => 'Sam Admin',
            'email' => 'sam.admin@register.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(409);

        $tenant = Tenant::query()->where('slug', 'same-email-co')->firstOrFail();

        $this->assertSame('sam.admin@register.test', $tenant->admin_email);
        $this->assertSame('Sam Admin', $tenant->admin_name);

        tenancy()->initialize($tenant);

        $admin = User::query()->where('email', 'sam.admin@register.test')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Hash::check('SecurePass123!', $admin->password));

        tenancy()->end();
    }
}
