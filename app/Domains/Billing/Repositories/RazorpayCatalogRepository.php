<?php

namespace App\Domains\Billing\Repositories;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error;

class RazorpayCatalogRepository
{
    private ?Api $client = null;

    public function isEnabled(): bool
    {
        return (bool) config('razorpay.enabled') && config('razorpay.key') && config('razorpay.secret');
    }

    public function retrievePlan(string $planId): ?array
    {
        try {
            return $this->client()->plan->fetch($planId)->toArray();
        } catch (Error) {
            return null;
        }
    }

    public function createPlan(string $name, int $amountMinor, string $currency, string $slug, string $interval): array
    {
        $period = $interval === 'year' ? 'yearly' : 'monthly';

        return $this->client()->plan->create([
            'period' => $period,
            'interval' => 1,
            'item' => [
                'name' => $name,
                'amount' => $amountMinor,
                'currency' => strtoupper($currency),
                'description' => "{$name} ({$period})",
            ],
            'notes' => [
                'plan_slug' => $slug,
                'billing_interval' => $interval,
                'managed_by' => 'helpdesk',
            ],
        ])->toArray();
    }

    public function createAddonPlan(string $name, int $amountMinor, string $currency, string $addonKey): array
    {
        return $this->client()->plan->create([
            'period' => 'monthly',
            'interval' => 1,
            'item' => [
                'name' => $name,
                'amount' => $amountMinor,
                'currency' => strtoupper($currency),
                'description' => "{$name} add-on",
            ],
            'notes' => [
                'addon_key' => $addonKey,
                'managed_by' => 'helpdesk',
            ],
        ])->toArray();
    }

    public function planMatches(?array $plan, int $amountMinor, string $currency, string $interval): bool
    {
        if ($plan === null) {
            return false;
        }

        $expectedPeriod = $interval === 'year' ? 'yearly' : 'monthly';

        if (($plan['period'] ?? null) !== $expectedPeriod) {
            return false;
        }

        if ((int) ($plan['item']['amount'] ?? -1) !== $amountMinor) {
            return false;
        }

        return strtoupper((string) ($plan['item']['currency'] ?? '')) === strtoupper($currency);
    }

    private function client(): Api
    {
        if ($this->client === null) {
            $this->client = new Api((string) config('razorpay.key'), (string) config('razorpay.secret'));
        }

        return $this->client;
    }
}
