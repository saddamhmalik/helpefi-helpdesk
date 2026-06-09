<?php

namespace Tests\Unit\Billing;

use App\Domains\Billing\Repositories\StripeBillingRepository;
use App\Domains\Billing\Services\PlatformPaymentService;
use App\Domains\Billing\Services\StripeBackfillService;
use App\Domains\Billing\Repositories\PlanRepository;
use Mockery;
use Tests\TestCase;

class StripeBackfillServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_backfill_requires_stripe_to_be_enabled(): void
    {
        $stripeBilling = Mockery::mock(StripeBillingRepository::class);
        $stripeBilling->shouldReceive('isEnabled')->once()->andReturn(false);

        $service = new StripeBackfillService(
            $stripeBilling,
            Mockery::mock(PlatformPaymentService::class),
            Mockery::mock(PlanRepository::class),
        );

        $this->expectException(\InvalidArgumentException::class);

        $service->backfill();
    }
}
