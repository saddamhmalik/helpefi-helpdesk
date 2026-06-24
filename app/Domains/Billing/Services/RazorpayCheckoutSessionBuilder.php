<?php

namespace App\Domains\Billing\Services;

use Illuminate\Validation\ValidationException;

class RazorpayCheckoutSessionBuilder
{
    public function build(
        array $razorpaySubscription,
        array $plan,
        string $customerEmail,
        string $customerName,
    ): array {
        $subscriptionId = (string) ($razorpaySubscription['id'] ?? '');

        if ($subscriptionId === '') {
            throw ValidationException::withMessages([
                'plan' => 'Unable to start Razorpay checkout. Please try again.',
            ]);
        }

        return [
            'key' => (string) config('razorpay.key'),
            'subscription_id' => $subscriptionId,
            'name' => (string) config('app.name'),
            'description' => ($plan['name'] ?? 'Plan').' subscription',
            'prefill' => [
                'email' => $customerEmail,
                'name' => $customerName,
            ],
            'theme' => [
                'color' => '#2563eb',
            ],
        ];
    }

    public function buildPlanSession(
        array $razorpaySubscription,
        array $plan,
        string $customerEmail,
        string $customerName,
        string $successRedirect = '/settings/billing?checkout=success&section=plans',
        string $cancelRedirect = '/settings/billing?checkout=cancelled&section=plans',
    ): array {
        return array_merge(
            $this->build($razorpaySubscription, $plan, $customerEmail, $customerName),
            [
                'redirect_on_success' => $successRedirect,
                'redirect_on_cancel' => $cancelRedirect,
            ],
        );
    }

    public function buildAddonSession(
        array $addonSubscription,
        array $addon,
        string $customerEmail,
        string $customerName,
    ): array {
        return array_merge(
            $this->build(
                $addonSubscription,
                ['name' => $addon['name'] ?? 'Add-on'],
                $customerEmail,
                $customerName,
            ),
            [
                'redirect_on_success' => '/settings/billing?checkout=success&section=addons',
                'redirect_on_cancel' => '/settings/billing?checkout=cancelled&section=addons',
            ],
        );
    }
}
