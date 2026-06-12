<?php

namespace Tests\Unit\Billing;

use App\Domains\Billing\Support\RazorpaySubscriptionCheckout;
use PHPUnit\Framework\TestCase;

class RazorpaySubscriptionCheckoutTest extends TestCase
{
    public function test_allows_standard_checkout_for_open_created_subscription(): void
    {
        $this->assertTrue(RazorpaySubscriptionCheckout::canAuthenticateViaStandardCheckout([
            'status' => 'created',
            'id' => 'sub_test',
            'expire_by' => now()->addDay()->getTimestamp(),
        ]));
    }

    public function test_rejects_expired_created_subscriptions(): void
    {
        $this->assertFalse(RazorpaySubscriptionCheckout::canAuthenticateViaStandardCheckout([
            'status' => 'created',
            'id' => 'sub_test',
            'expire_by' => now()->subHour()->getTimestamp(),
        ]));
    }

    public function test_rejects_created_subscriptions_without_expiry(): void
    {
        $this->assertFalse(RazorpaySubscriptionCheckout::canAuthenticateViaStandardCheckout([
            'status' => 'created',
            'id' => 'sub_test',
        ]));
    }

    public function test_marks_stale_created_subscriptions_for_reset(): void
    {
        $this->assertTrue(RazorpaySubscriptionCheckout::shouldResetIncompleteSubscription([
            'status' => 'created',
            'id' => 'sub_test',
            'expire_by' => now()->subHour()->getTimestamp(),
        ]));

        $this->assertTrue(RazorpaySubscriptionCheckout::shouldResetIncompleteSubscription([
            'status' => 'expired',
        ]));
    }
}
