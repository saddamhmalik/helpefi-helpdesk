<?php

namespace Tests\Feature;

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
        ])->assertRedirect(route('setup'));
    }

    public function test_admin_can_finish_setup(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        $this->actingAs($admin)
            ->tenantPost('/setup/finish')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->tenantGet('/setup')
            ->assertRedirect(route('dashboard'));
    }
}
