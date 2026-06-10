<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Channels\Models\MessagingSetting;
use App\Domains\Security\Models\SecuritySetting;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TenantBootstrapSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class Phase6EnterpriseTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            TenantBootstrapSeeder::class,
            TicketLookupSeeder::class,
            ChannelSeeder::class,
            SlaSeeder::class,
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => 'enterprise',
                'status' => Subscription::STATUS_ACTIVE,
                'renews_at' => now()->addMonth(),
            ],
        );
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
    }

    public function test_admin_can_save_sso_settings(): void
    {
        $this->actingAs($this->admin())
            ->tenantPut('/settings/security/sso', [
                'sso_enabled' => true,
                'sso_protocol' => 'oidc',
                'sso_config' => [
                    'preset' => 'google',
                    'client_id' => 'google-client-id',
                    'client_secret' => 'google-client-secret',
                    'button_label' => 'Sign in with Google',
                    'auto_provision' => true,
                    'allowed_domains' => ['example.com'],
                ],
            ])
            ->assertRedirect();

        $setting = SecuritySetting::query()->first();

        $this->assertTrue($setting->sso_enabled);
        $this->assertSame('oidc', $setting->sso_protocol);
        $this->assertSame('google-client-id', $setting->sso_config['client_id']);
    }

    public function test_admin_can_save_messaging_and_marketplace_settings(): void
    {
        $this->actingAs($this->admin())
            ->tenantPut('/settings/integrations/shopify', [
                'shop' => 'demo.myshopify.com',
                'access_token' => 'shpat_test',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->tenantPut('/settings/messaging', [
                'is_active' => true,
                'account_sid' => 'AC123',
                'auth_token' => 'secret',
                'whatsapp_from' => '+14155238886',
                'sms_from' => '+14155238886',
            ])
            ->assertRedirect();

        $setting = MessagingSetting::query()->first();

        $this->assertTrue($setting->is_active);
        $this->assertSame('AC123', $setting->account_sid);
    }

    public function test_login_page_exposes_sso_button_when_enabled(): void
    {
        SecuritySetting::query()->firstOrCreate([])->update([
            'sso_enabled' => true,
            'sso_protocol' => 'oidc',
            'sso_config' => [
                'preset' => 'google',
                'client_id' => 'abc',
                'client_secret' => 'secret',
            ],
        ]);

        $this->tenantGet('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('sso.enabled', true));
    }
}
