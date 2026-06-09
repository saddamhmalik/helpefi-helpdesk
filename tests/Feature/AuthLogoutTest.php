<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class AuthLogoutTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    public function test_agent_can_logout_without_csrf_error(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        $this->actingAs($admin)
            ->tenantPost('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
