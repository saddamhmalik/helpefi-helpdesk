<?php

namespace Tests\Unit\Billing;

use App\Domains\Billing\Support\RazorpaySubscriptionCheckout;
use PHPUnit\Framework\TestCase;

class RazorpaySubscriptionCheckoutTest extends TestCase
{
    public function test_returns_hosted_page_url_for_open_created_subscription(): void
    {
        $url = RazorpaySubscriptionCheckout::hostedPageUrl([
            'status' => 'created',
            'short_url' => 'https://api.razorpay.com/v1/t/subscriptions/sub_test',
            'expire_by' => now()->addDay()->getTimestamp(),
        ]);

        $this->assertSame('https://api.razorpay.com/v1/t/subscriptions/sub_test', $url);
    }

    public function test_rejects_expired_checkout_links(): void
    {
        $url = RazorpaySubscriptionCheckout::hostedPageUrl([
            'status' => 'created',
            'short_url' => 'https://api.razorpay.com/v1/t/subscriptions/sub_test',
            'expire_by' => now()->subHour()->getTimestamp(),
        ]);

        $this->assertNull($url);
    }

    public function test_marks_stale_created_subscriptions_for_reset(): void
    {
        $this->assertTrue(RazorpaySubscriptionCheckout::shouldResetIncompleteSubscription([
            'status' => 'created',
            'short_url' => 'https://api.razorpay.com/v1/t/subscriptions/sub_test',
            'expire_by' => now()->subHour()->getTimestamp(),
        ]));

        $this->assertTrue(RazorpaySubscriptionCheckout::shouldResetIncompleteSubscription([
            'status' => 'expired',
        ]));
    }
}
