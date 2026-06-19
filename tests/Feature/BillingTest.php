<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\AssetType;
use App\Domains\Billing\Models\PlatformPayment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Sla\Models\BusinessHours;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Tickets\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\EmailSeeder;
use Database\Seeders\SlaSeeder;
use Database\Seeders\TicketLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

class BillingTest extends TenantTestCase
{
    use RefreshDatabase;

    private function setPlan(string $plan, array $activeAddons = []): void
    {
        Subscription::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'plan' => $plan,
                'status' => Subscription::STATUS_ACTIVE,
                'renews_at' => now()->addMonth(),
                'active_addons' => $activeAddons,
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

    public function test_billing_page_includes_payment_history(): void
    {
        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        PlatformPayment::query()->create([
            'tenant_id' => tenant('id'),
            'razorpay_payment_id' => 'pay_test_billing_history',
            'amount' => 9900,
            'currency' => 'INR',
            'status' => PlatformPayment::STATUS_PAID,
            'plan' => 'professional',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->tenantGet('/settings/billing?section=payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payments', 1)
                ->where('payments.0.amount', 9900));
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
                'currency' => ['code', 'symbol', 'name'],
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

    public function test_starter_plan_blocks_second_brand(): void
    {
        $this->setPlan('starter');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantPost('/settings/brands', [
                'name' => 'Globex',
                'slug' => 'globex',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_starter_plan_blocks_service_catalog_management(): void
    {
        $this->setPlan('starter');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();

        $this->actingAs($admin)
            ->tenantPost('/settings/service-catalog/categories', [
                'name' => 'IT Services',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_starter_plan_blocks_business_hours_update(): void
    {
        $this->seed(SlaSeeder::class);
        $this->setPlan('starter');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $hours = BusinessHours::query()->firstOrFail();

        $this->actingAs($admin)
            ->tenantPut("/settings/sla/business-hours/{$hours->id}", [
                'name' => 'Updated Hours',
                'timezone' => 'UTC',
                'schedule' => [
                    'mon' => ['start' => '09:00', 'end' => '17:00'],
                    'tue' => null,
                    'wed' => null,
                    'thu' => null,
                    'fri' => null,
                    'sat' => null,
                    'sun' => null,
                ],
            ])
            ->assertForbidden();
    }

    public function test_starter_plan_blocks_email_inbox_creation(): void
    {
        $this->seed(EmailSeeder::class);
        $this->setPlan('starter');

        $admin = User::query()->where('email', 'admin@helpdesk.test')->first();
        $brandId = \App\Domains\Brands\Models\Brand::query()->value('id');

        $this->actingAs($admin)
            ->tenantPost('/settings/email/inboxes', [
                'name' => 'Billing',
                'address' => 'billing@helpdesk.test',
                'brand_id' => $brandId,
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_starter_plan_does_not_apply_sla_timers(): void
    {
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('starter');

        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();

        $ticket = Ticket::query()->create([
            'number' => 'HD-01001',
            'subject' => 'SLA skipped',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        app(SlaService::class)->applyToTicket($ticket);

        $this->assertDatabaseMissing('ticket_sla_timers', ['ticket_id' => $ticket->id]);
    }

    public function test_starter_monthly_ticket_limit_blocks_new_tickets(): void
    {
        config(['plans.starter.limits.tickets_monthly' => 2]);
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('starter');

        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $agent = User::factory()->create();

        Ticket::query()->create([
            'number' => 'HD-01002',
            'subject' => 'First',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        Ticket::query()->create([
            'number' => 'HD-01003',
            'subject' => 'Second',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $this->actingAs($agent)
            ->tenantPost('/tickets', [
                'subject' => 'Third ticket',
                'description' => 'Should be blocked',
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
            ])
            ->assertSessionHasErrors('subject');
    }

    public function test_starter_monthly_ticket_limit_blocks_split(): void
    {
        config(['plans.starter.limits.tickets_monthly' => 1]);
        $this->seed(TicketLookupSeeder::class);
        $this->setPlan('starter');

        $status = TicketStatus::query()->where('slug', 'open')->firstOrFail();
        $priority = TicketPriority::query()->where('slug', 'normal')->firstOrFail();
        $agent = User::factory()->create();

        $ticket = Ticket::query()->create([
            'number' => 'HD-01004',
            'subject' => 'Split me',
            'ticket_status_id' => $status->id,
            'ticket_priority_id' => $priority->id,
        ]);

        $message = TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Customer reply',
            'is_internal' => false,
        ]);

        $this->actingAs($agent)
            ->tenantPost("/tickets/{$ticket->id}/split", [
                'from_message_id' => $message->id,
            ])
            ->assertSessionHasErrors('subject');
    }

    public function test_customer_accounts_do_not_count_toward_agent_limit(): void
    {
        $this->setPlan('starter');

        User::factory()->create(['email' => 'agent-two@test.com']);
        User::factory()->create(['email' => 'agent-three@test.com']);

        $admin = User::query()->where('email', 'admin@helpdesk.test')->firstOrFail();

        for ($index = 1; $index <= 5; $index++) {
            User::factory()->create([
                'email' => "customer-{$index}@test.com",
            ])->assignRole('customer');
        }

        $this->actingAs($admin)
            ->tenantPost('/settings/members/invite', [
                'email' => 'fourth-agent@test.com',
                'role' => 'agent',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_professional_ai_copilot_addon_grants_ai_feature(): void
    {
        config(['razorpay.enabled' => false]);
        $this->setPlan('professional');
        $billing = app(BillingService::class);

        $this->assertFalse($billing->canUseFeature('ai'));

        $billing->activateAddon('ai_copilot');

        $this->assertTrue(app(BillingService::class)->canUseFeature('ai'));
    }

    public function test_professional_integrations_addon_grants_integrations_feature(): void
    {
        config(['razorpay.enabled' => false]);
        $this->setPlan('professional');
        $billing = app(BillingService::class);

        $this->assertFalse($billing->canUseFeature('integrations'));

        $billing->activateAddon('integrations');

        $this->assertTrue(app(BillingService::class)->canUseFeature('integrations'));
    }

    public function test_enterprise_plan_cannot_purchase_ai_copilot_addon(): void
    {
        $this->setPlan('enterprise');

        $this->expectException(ValidationException::class);

        app(BillingService::class)->activateAddon('ai_copilot');
    }

    public function test_enterprise_plan_marks_ai_addon_as_included_in_plan(): void
    {
        $this->setPlan('enterprise');
        $billing = app(BillingService::class)->snapshot();

        $aiAddon = collect($billing['available_addons'])->firstWhere('key', 'ai_copilot');

        $this->assertNotNull($aiAddon);
        $this->assertTrue($aiAddon['included_in_plan']);
        $this->assertTrue($aiAddon['active']);
    }
}
