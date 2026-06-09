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

    public function test_stripe_invoice_paid_is_recorded_with_tenant_details(): void
    {
        $tenant = app(TenantProvisioningService::class)->provision(
            organizationName: 'Paying Co',
            slug: 'paying-co',
            adminName: 'Pay Admin',
            adminEmail: 'pay@test.com',
            adminPassword: 'password123',
        );

        $tenant->update(['stripe_id' => 'cus_test_123']);

        Subscription::query()->where('tenant_id', $tenant->id)->update([
            'plan' => 'enterprise',
            'status' => Subscription::STATUS_ACTIVE,
            'stripe_subscription_id' => 'sub_test_123',
        ]);

        $invoice = (object) [
            'id' => 'in_test_123',
            'customer' => 'cus_test_123',
            'subscription' => 'sub_test_123',
            'payment_intent' => 'pi_test_123',
            'amount_paid' => 19900,
            'currency' => 'inr',
            'customer_email' => 'pay@test.com',
            'customer_name' => 'Pay Admin',
            'number' => 'INV-1001',
            'hosted_invoice_url' => 'https://stripe.test/invoice',
            'invoice_pdf' => 'https://stripe.test/invoice.pdf',
            'status_transitions' => (object) ['paid_at' => now()->timestamp],
            'lines' => (object) ['data' => [(object) ['description' => 'Enterprise plan']]],
        ];

        app(PlatformPaymentService::class)->recordFromStripeInvoice($invoice);

        $this->assertDatabaseHas('platform_payments', [
            'stripe_invoice_id' => 'in_test_123',
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
