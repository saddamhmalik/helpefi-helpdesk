<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Services\TenantWelcomeTokenService;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class TenantWelcomeTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    public function test_welcome_token_logs_in_admin_and_redirects_to_setup(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
        $token = app(TenantWelcomeTokenService::class)->issue(tenant('id'), $admin->email);

        $this->tenantGet('/welcome?token='.urlencode($token))
            ->assertRedirect(route('setup'));

        $this->assertAuthenticatedAs($admin);

        $this->actingAs($admin)
            ->tenantGet('/setup')
            ->assertOk();
    }

    public function test_invalid_welcome_token_is_rejected(): void
    {
        $this->tenantGet('/welcome?token=invalid-token')
            ->assertForbidden();
    }

    public function test_welcome_token_cannot_be_reused(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
        $token = app(TenantWelcomeTokenService::class)->issue(tenant('id'), $admin->email);

        $this->tenantGet('/welcome?token='.urlencode($token))->assertRedirect(route('setup'));

        auth()->logout();

        $this->tenantGet('/welcome?token='.urlencode($token))->assertForbidden();
    }
}
