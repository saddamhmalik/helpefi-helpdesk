<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Subscription;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class TrialSubscriptionTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([TicketLookupSeeder::class, ChannelSeeder::class, SlaSeeder::class]);
    }

    public function test_expired_trial_redirects_to_subscription_required(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        Subscription::query()->where('tenant_id', tenant('id'))->update([
            'status' => Subscription::STATUS_TRIAL,
            'plan' => null,
            'trial_ends_at' => now()->subDay(),
        ]);

        app(\App\Domains\Tenancy\Services\TenantSetupService::class)->finish();

        $this->actingAs($admin)
            ->tenantGet('/dashboard')
            ->assertRedirect(route('subscription.required'));
    }

    public function test_admin_can_still_access_billing_when_trial_expired(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        Subscription::query()->where('tenant_id', tenant('id'))->update([
            'status' => Subscription::STATUS_TRIAL,
            'plan' => null,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->tenantGet('/settings/billing')
            ->assertOk();
    }

    public function test_cannot_purchase_plan_during_active_trial(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        Subscription::query()->where('tenant_id', tenant('id'))->update([
            'status' => Subscription::STATUS_TRIAL,
            'plan' => null,
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->actingAs($admin)
            ->tenantPut('/settings/billing/plan', ['plan' => 'professional'])
            ->assertSessionHasErrors('plan');
    }

    public function test_activating_plan_restores_access(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        app(\App\Domains\Tenancy\Services\TenantSetupService::class)->finish();

        Subscription::query()->where('tenant_id', tenant('id'))->update([
            'status' => Subscription::STATUS_TRIAL,
            'plan' => null,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->tenantPut('/settings/billing/plan', ['plan' => 'professional'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->tenantGet('/dashboard')
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => tenant('id'),
            'plan' => 'professional',
            'status' => Subscription::STATUS_ACTIVE,
        ], 'central');
    }
}
