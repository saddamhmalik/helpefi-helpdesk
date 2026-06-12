<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\PlatformPayment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\PlatformPaymentService;
use App\Domains\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformPermissionSeeder::class, PlatformUserSeeder::class]);
    }

    private function adminLogin(): void
    {
        $this->post('http://'.config('tenancy.central_app_domain').'/admin/login', [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_platform_admin_can_view_payments_page(): void
    {
        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Admin/Payments/Index'));
    }

    public function test_razorpay_payment_is_recorded_with_tenant_details(): void
    {
        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Paying Co',
            slug: 'paying-co',
            adminName: 'Pay Admin',
            adminEmail: 'pay@test.com',
            adminPassword: 'password123',
        );

        $tenant->update(['razorpay_customer_id' => 'cust_test_123']);

        Subscription::query()->where('tenant_id', $tenant->id)->update([
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_ACTIVE,
            'razorpay_subscription_id' => 'sub_test_123',
        ]);

        $payment = [
            'id' => 'pay_test_123',
            'customer_id' => 'cust_test_123',
            'subscription_id' => 'sub_test_123',
            'order_id' => 'order_test_123',
            'amount' => 19900,
            'currency' => 'inr',
            'email' => 'pay@test.com',
            'description' => 'Enterprise plan',
            'created_at' => now()->timestamp,
            'notes' => ['plan' => 'enterprise'],
        ];

        $subscription = [
            'id' => 'sub_test_123',
            'notes' => [
                'tenant_id' => $tenant->id,
                'plan' => 'enterprise',
            ],
        ];

        app(PlatformPaymentService::class)->recordFromRazorpayPayment($payment, $subscription);

        $this->assertDatabaseHas('platform_payments', [
            'razorpay_payment_id' => 'pay_test_123',
            'tenant_id' => $tenant->id,
            'plan' => 'enterprise',
            'amount' => 19900,
            'currency' => 'INR',
            'status' => PlatformPayment::STATUS_PAID,
            'customer_email' => 'pay@test.com',
        ], 'central');

        $this->adminLogin();

        $this->get('http://'.config('tenancy.central_app_domain').'/admin/payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payments.data', 1)
                ->where('payments.data.0.tenant.slug', 'paying-co')
                ->where('payments.data.0.plan_name', 'Enterprise')
                ->where('payments.data.0.amount', 19900));
    }
}
