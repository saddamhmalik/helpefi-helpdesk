<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\AssetType;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class BillingTest extends TenantTestCase
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

    public function test_admin_can_view_billing_settings(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantGet('/settings/billing')
            ->assertOk();
    }

    public function test_agent_cannot_view_billing_settings(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantGet('/settings/billing')
            ->assertForbidden();
    }

    public function test_admin_can_change_plan(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $this->setPlan('starter');

        $this->actingAs($admin)
            ->tenantPut('/settings/billing/plan', ['plan' => 'enterprise'])
            ->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => tenant('id'),
            'plan' => 'enterprise',
        ], 'central');
    }

    public function test_starter_plan_blocks_ai_suggest_reply(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('starter');

        $status = TicketStatus::query()->where('slug', 'open')->first();
        $priority = TicketPriority::query()->where('slug', 'normal')->first();
        $agent = User::factory()->create();

        $ticket = \App\Domains\Tickets\Models\Ticket::query()->create([
            'number' => 'HD-00999',
            'subject' => 'Test',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($agent)
            ->tenantPost("/tickets/{$ticket->id}/ai/suggest-reply")
            ->assertForbidden();
    }

    public function test_starter_plan_blocks_asset_creation(): void
    {
        $this->setPlan('starter');

        $type = AssetType::query()->firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop']);
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->tenantPost('/assets', [
                'asset_type_id' => $type->id,
                'name' => 'Blocked laptop',
                'status' => 'in_stock',
            ])
            ->assertForbidden();
    }

    public function test_agent_limit_blocks_invite_on_starter_plan(): void
    {
        $this->setPlan('starter');

        User::factory()->admin()->create(['email' => 'one@test.com']);
        User::factory()->create(['email' => 'two@test.com']);
        User::factory()->create(['email' => 'three@test.com']);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->tenantPost('/settings/members/invite', [
                'email' => 'fourth@test.com',
                'role' => 'agent',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_api_returns_billing_snapshot(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $login = $this->tenantPostJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->withToken($login->json('token'))
            ->tenantGetJson('/api/v1/billing')
            ->assertOk()
            ->assertJsonStructure([
                'plan' => ['slug', 'name', 'price'],
                'usage' => ['agents', 'tickets_monthly'],
                'limits',
                'features',
            ]);
    }

    public function test_billing_service_reports_feature_access(): void
    {
        $this->setPlan('professional');

        $billing = app(BillingService::class);

        $this->assertTrue($billing->canUseFeature('automation'));
        $this->assertFalse($billing->canUseFeature('ai'));
    }
}
