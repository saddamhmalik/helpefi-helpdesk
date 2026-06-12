<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Repositories\PlanRepository;
use App\Domains\Billing\Repositories\RazorpayCatalogRepository;
use App\Domains\Billing\Repositories\SubscriptionRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Domains\Tenancy\Support\PlanCatalogDefinition;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Errors\Error;

class RazorpayPlanSyncService
{
    public function __construct(private RazorpayCatalogRepository $razorpayCatalog)
    {
    }

    public function isEnabled(): bool
    {
        return $this->razorpayCatalog->isEnabled();
    }

    public function syncCatalog(array $catalog, string $currency): array
    {
        if (! $this->isEnabled()) {
            return $catalog;
        }

        $synced = [];

        foreach ($catalog as $slug => $plan) {
            try {
                $synced[$slug] = $this->syncPlan($slug, $plan, $currency);
            } catch (Error $exception) {
                throw ValidationException::withMessages([
                    'plans' => "Razorpay sync failed for the {$slug} plan: {$exception->getMessage()}",
                ]);
            }
        }

        return $synced;
    }

    private function syncPlan(string $slug, array $plan, string $currency): array
    {
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

        return array_merge($plan, [
            'razorpay_plan_id' => $monthlyPlanId,
            'razorpay_plan_id_monthly' => $monthlyPlanId,
            'razorpay_plan_id_yearly' => $yearlyPlanId,
            'price' => (int) ($plan['price_monthly'] ?? $plan['price'] ?? 0),
        ]);
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

        return (string) ($created['id'] ?? '');
    }
}
