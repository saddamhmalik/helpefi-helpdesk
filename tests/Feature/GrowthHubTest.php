<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Services\TenantSetupService;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class GrowthHubTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    private function finishSetup(): void
    {
        $setup = app(TenantSetupService::class);

        foreach (['business_hours', 'email', 'chat_widget', 'invite_team', 'sla_policies'] as $step) {
            $setup->completeStep($step);
        }

        $setup->finish();
    }

    public function test_admin_can_view_growth_hub(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();
        $this->finishSetup();

        $this->actingAs($admin)
            ->tenantGet('/growth')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Growth/Index')
                ->has('hub.billing')
                ->has('hub.setup_health')
                ->has('hub.engagement')
                ->has('hub.ai_usage')
                ->has('hub.ai_deflection')
                ->has('hub.kb_deflection'));
    }

    public function test_non_admin_cannot_view_growth_hub(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('agent');
        $this->finishSetup();

        $this->actingAs($agent)
            ->tenantGet('/growth')
            ->assertForbidden();
    }
}
