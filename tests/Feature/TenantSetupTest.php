<?php

namespace Tests\Feature;

use App\Domains\Channels\Models\EmailInbox;
use App\Domains\Channels\Models\MailSetting;
use App\Domains\Tenancy\Services\TenantSetupService;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class TenantSetupTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    public function test_admin_is_redirected_to_setup_after_login(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        $this->tenantPost('/login', [
            'email' => 'admin@helpdesk.test',
            'password' => 'password',
        ])->assertRedirect($this->tenantUrl('/setup'));
    }

    public function test_admin_is_redirected_to_setup_from_dashboard_until_dismissed(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        $this->actingAs($admin)
            ->tenantGet('/dashboard')
            ->assertRedirect($this->tenantUrl('/setup'));
    }

    public function test_admin_can_access_settings_during_setup(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        $this->actingAs($admin)
            ->tenantGet('/settings/email')
            ->assertOk();
    }

    public function test_admin_can_finish_setup(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        $this->actingAs($admin)
            ->tenantPost('/setup/finish')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->tenantGet('/setup')
            ->assertOk();
    }

    public function test_admin_can_access_dashboard_after_setup_is_dismissed(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        app(TenantSetupService::class)->finish();

        $this->actingAs($admin)
            ->tenantGet('/dashboard')
            ->assertOk();
    }

    public function test_setup_warnings_include_email_when_defaults_are_unconfigured(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        app(TenantSetupService::class)->finish();

        $this->actingAs($admin)
            ->tenantGet('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('setupWarnings', function ($warnings) {
                    $keys = collect($warnings)->pluck('key');

                    return $keys->contains('email');
                })
            );
    }

    public function test_setup_warnings_clear_when_email_is_configured(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        EmailInbox::query()->where('is_active', true)->update([
            'address' => 'support@company.test',
        ]);

        $mail = MailSetting::query()->firstOrFail();
        $mail->update([
            'enabled' => true,
            'driver' => 'smtp',
            'host' => 'smtp.company.test',
            'from_address' => 'support@company.test',
        ]);

        app(TenantSetupService::class)->finish();

        $this->actingAs($admin)
            ->tenantGet('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('setupWarnings', function ($warnings) {
                    $keys = collect($warnings)->pluck('key');

                    return ! $keys->contains('email');
                })
            );
    }

    public function test_setup_warnings_include_invite_team_when_only_admin_exists(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        app(TenantSetupService::class)->finish();

        $this->actingAs($admin)
            ->tenantGet('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('setupWarnings', function ($warnings) {
                    return collect($warnings)->pluck('key')->contains('invite_team');
                })
            );
    }
}
