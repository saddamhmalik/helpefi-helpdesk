<?php

namespace App\Domains\Billing\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Errors\Error;

class RazorpayCustomerService
{
    public function __construct(private RazorpayApiClient $api)
    {
    }

    public function ensureCustomer(Tenant $tenant, string $email): string
    {
        if ($tenant->razorpay_customer_id) {
            try {
                $this->api->client()->customer->fetch($tenant->razorpay_customer_id);

                return $tenant->razorpay_customer_id;
            } catch (Error $exception) {
                Log::warning('Razorpay customer fetch failed, recreating customer', [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $tenant->razorpay_customer_id,
                    'message' => $exception->getMessage(),
                ]);

                $tenant->update(['razorpay_customer_id' => null]);
            }
        }

        $customer = $this->api->client()->customer->create([
            'email' => $email,
            'name' => $tenant->name,
            'fail_existing' => '0',
            'notes' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ])->toArray();

        $customerId = (string) ($customer['id'] ?? '');

        if ($customerId === '') {
            throw ValidationException::withMessages([
                'plan' => 'Unable to start checkout. Please try again or contact support.',
            ]);
        }

        $tenant->update(['razorpay_customer_id' => $customerId]);

        return $customerId;
    }
}
