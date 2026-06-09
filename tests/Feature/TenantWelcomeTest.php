<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TenantTestCase;

class TenantWelcomeTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    public function test_signed_welcome_url_logs_in_admin_and_redirects_to_setup(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        URL::forceRootUrl('http://'.$this->tenantDomain());

        $url = URL::temporarySignedRoute(
            'welcome',
            now()->addMinutes(15),
            ['email' => $admin->email],
            absolute: false,
        );

        $this->tenantGet($url)
            ->assertRedirect(route('setup'));

        $this->assertAuthenticatedAs($admin);

        $this->actingAs($admin)
            ->tenantGet('/setup')
            ->assertOk();
    }

    public function test_unsigned_welcome_url_is_rejected(): void
    {
        $this->tenantGet('/welcome?email=admin@helpdesk.test')
            ->assertForbidden();
    }
}
