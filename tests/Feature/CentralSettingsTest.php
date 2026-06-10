<?php

namespace Tests\Feature;

use App\Domains\Billing\Services\StripePlanSyncService;
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
            'stripe.enabled' => false,
            'stripe.secret' => null,
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

    public function test_admin_settings_update_syncs_plans_with_stripe_when_enabled(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.secret' => 'sk_test_example',
        ]);

        $this->mock(StripePlanSyncService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('syncCatalog')
                ->once()
                ->andReturnUsing(fn (array $catalog) => collect($catalog)
                    ->map(fn (array $plan, string $slug) => array_merge($plan, [
                        'stripe_product_id' => "prod_{$slug}",
                        'stripe_price_id' => "price_monthly_{$slug}",
                        'stripe_price_id_monthly' => "price_monthly_{$slug}",
                        'stripe_price_id_yearly' => "price_yearly_{$slug}",
                    ]))
                    ->all());
        });

        $this->adminLogin();

        $this->put('http://'.config('tenancy.central_app_domain').'/admin/settings', [
            'trial_days' => 14,
            'tenant_purge_grace_days' => 15,
            'tenant_purge_enabled' => true,
            'currency' => 'USD',
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

        $this->assertSame('prod_starter', $starter['stripe_product_id']);
        $this->assertSame('price_monthly_starter', $starter['stripe_price_id']);
        $this->assertSame('price_yearly_starter', $starter['stripe_price_id_yearly']);
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
