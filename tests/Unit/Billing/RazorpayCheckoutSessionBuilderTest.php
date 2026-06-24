<?php

namespace Tests\Unit\Billing;

use App\Domains\Billing\Services\RazorpayCheckoutSessionBuilder;
use Tests\TestCase;

class RazorpayCheckoutSessionBuilderTest extends TestCase
{
    public function test_build_plan_session_includes_redirect_urls(): void
    {
        config(['razorpay.key' => 'rzp_test_key', 'app.name' => 'Helpdesk']);

        $builder = new RazorpayCheckoutSessionBuilder;
        $session = $builder->buildPlanSession(
            ['id' => 'sub_test123'],
            ['name' => 'Pro'],
            'billing@example.com',
            'Billing User',
            '/billing/success',
            '/billing/cancel',
        );

        $this->assertSame('rzp_test_key', $session['key']);
        $this->assertSame('sub_test123', $session['subscription_id']);
        $this->assertSame('Pro subscription', $session['description']);
        $this->assertSame('billing@example.com', $session['prefill']['email']);
        $this->assertSame('/billing/success', $session['redirect_on_success']);
        $this->assertSame('/billing/cancel', $session['redirect_on_cancel']);
    }

    public function test_build_addon_session_uses_addon_name(): void
    {
        config(['razorpay.key' => 'rzp_test_key', 'app.name' => 'Helpdesk']);

        $builder = new RazorpayCheckoutSessionBuilder;
        $session = $builder->buildAddonSession(
            ['id' => 'sub_addon123'],
            ['name' => 'AI Copilot'],
            'billing@example.com',
            'Billing User',
        );

        $this->assertSame('AI Copilot subscription', $session['description']);
        $this->assertStringContainsString('addons', $session['redirect_on_success']);
    }
}
