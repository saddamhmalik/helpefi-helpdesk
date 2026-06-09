<?php

namespace Tests\Unit\Billing;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Billing\Services\SubscriptionLifecycleService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class SubscriptionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_cancelled_subscription_keeps_access_during_grace_period(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        config(['billing.cancellation_grace_days' => 3]);

        $tenant = Tenant::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Grace Co',
            'slug' => 'grace-co',
        ]);

        $subscription = Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'professional',
                'status' => Subscription::STATUS_CANCELLED,
                'access_ends_at' => Carbon::parse('2026-06-11 12:00:00'),
            ],
        );

        $this->assertTrue($subscription->isAccessible());
        $this->assertTrue($subscription->isInGracePeriod());
        $this->assertSame(2, $subscription->graceDaysRemaining());

        Carbon::setTestNow();
    }

    public function test_enforce_grace_blocks_workspace_after_access_ends(): void
    {
        config(['billing.cancellation_grace_days' => 3]);

        $tenant = Tenant::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Expired Co',
            'slug' => 'expired-co',
            'is_blocked' => false,
        ]);

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan' => 'professional',
                'status' => Subscription::STATUS_CANCELLED,
                'access_ends_at' => now()->subDay(),
            ],
        );

        $service = new SubscriptionLifecycleService(
            new SubscriptionRepository,
            Mockery::mock(PlanRepository::class),
        );

        $this->assertSame(1, $service->enforceExpiredGrace());
        $this->assertTrue($tenant->fresh()->is_blocked);
    }
}
