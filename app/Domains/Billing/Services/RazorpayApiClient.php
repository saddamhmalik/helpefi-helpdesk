<?php

namespace App\Domains\Billing\Services;

use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error;

class RazorpayApiClient
{
    private ?Api $client = null;

    public function isEnabled(): bool
    {
        return (bool) config('razorpay.enabled') && config('razorpay.key') && config('razorpay.secret');
    }

    public function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'plan' => 'Razorpay billing is not configured.',
            ]);
        }
    }

    public function client(): Api
    {
        if ($this->client === null) {
            $this->client = new Api((string) config('razorpay.key'), (string) config('razorpay.secret'));
        }

        return $this->client;
    }

    public function fetchedSubscription(string $subscriptionId)
    {
        return $this->client()->subscription->fetch($subscriptionId);
    }

    public function fetchSubscriptionArray(string $subscriptionId): ?array
    {
        try {
            return $this->fetchedSubscription($subscriptionId)->toArray();
        } catch (Error) {
            return null;
        }
    }

    public function fetchPaymentArray(string $paymentId): ?array
    {
        try {
            return $this->client()->payment->fetch($paymentId)->toArray();
        } catch (Error) {
            return null;
        }
    }
}
