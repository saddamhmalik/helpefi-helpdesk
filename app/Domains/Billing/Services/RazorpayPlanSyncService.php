<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\RazorpayCatalogRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Errors\Error;

class RazorpayPlanSyncService
{
    private array $skipped = [];

    public function __construct(private RazorpayCatalogRepository $razorpayCatalog)
    {
    }

    public function isEnabled(): bool
    {
        return $this->razorpayCatalog->isEnabled();
    }

    public function skipped(): array
    {
        return array_values(array_unique($this->skipped));
    }

    public function syncCatalog(array $catalog, string $currency, ?string $indiaCurrency = null): array
    {
        $this->skipped = [];

        if (! $this->isEnabled()) {
            return $catalog;
        }

        $synced = [];

        foreach ($catalog as $slug => $plan) {
            $synced[$slug] = $this->syncPlan($slug, $plan, $currency, $indiaCurrency);
        }

        return $synced;
    }

    private function syncPlan(string $slug, array $plan, string $currency, ?string $indiaCurrency): array
    {
        if (! empty($plan['custom_pricing'])) {
            return $plan;
        }

        $monthlyPlanId = $this->resolvePlanId(
            $slug,
            $plan,
            max(0, (int) ($plan['price_monthly'] ?? $plan['price'] ?? 0)) * 100,
            $currency,
            'month',
            'razorpay_plan_id_monthly',
            'razorpay_plan_id',
        );

        $yearlyPlanId = $this->resolvePlanId(
            $slug,
            $plan,
            max(0, (int) ($plan['price_yearly'] ?? 0)) * 100,
            $currency,
            'year',
            'razorpay_plan_id_yearly',
        );

        $plan = array_merge($plan, [
            'razorpay_plan_id' => $monthlyPlanId,
            'razorpay_plan_id_monthly' => $monthlyPlanId,
            'razorpay_plan_id_yearly' => $yearlyPlanId,
            'price' => (int) ($plan['price_monthly'] ?? $plan['price'] ?? 0),
        ]);

        if ($indiaCurrency !== null && strtoupper($indiaCurrency) !== strtoupper($currency)) {
            $plan = array_merge($plan, [
                'razorpay_plan_id_monthly_india' => $this->resolvePlanId(
                    $slug,
                    $plan,
                    max(0, (int) ($plan['price_monthly_india'] ?? 0)) * 100,
                    $indiaCurrency,
                    'month',
                    'razorpay_plan_id_monthly_india',
                ),
                'razorpay_plan_id_yearly_india' => $this->resolvePlanId(
                    $slug,
                    $plan,
                    max(0, (int) ($plan['price_yearly_india'] ?? 0)) * 100,
                    $indiaCurrency,
                    'year',
                    'razorpay_plan_id_yearly_india',
                ),
            ]);
        }

        return $plan;
    }

    private function resolvePlanId(
        string $slug,
        array $plan,
        int $amountMinor,
        string $currency,
        string $interval,
        string $primaryKey,
        ?string $fallbackKey = null,
    ): ?string {
        if ($amountMinor <= 0) {
            return null;
        }

        $existingPlanId = $plan[$primaryKey] ?? ($fallbackKey ? ($plan[$fallbackKey] ?? null) : null);

        try {
            if (is_string($existingPlanId) && $existingPlanId !== '') {
                $existingPlan = $this->razorpayCatalog->retrievePlan($existingPlanId);

                if ($this->razorpayCatalog->planMatches($existingPlan, $amountMinor, $currency, $interval)) {
                    return $existingPlanId;
                }
            }

            $created = $this->razorpayCatalog->createPlan(
                (string) ($plan['name'] ?? ucfirst($slug)),
                $amountMinor,
                $currency,
                $slug,
                $interval,
            );

            return (string) ($created['id'] ?? '') ?: null;
        } catch (Error $exception) {
            Log::warning('Razorpay plan sync skipped for unsupported currency or API error', [
                'slug' => $slug,
                'currency' => $currency,
                'interval' => $interval,
                'message' => $exception->getMessage(),
            ]);

            $this->skipped[] = sprintf('%s plan (%s %sly)', $slug, strtoupper($currency), $interval);

            return is_string($existingPlanId) && $existingPlanId !== '' ? $existingPlanId : null;
        }
    }
}
