<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Repositories\RazorpayCatalogRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Errors\Error;

class RazorpayAddonSyncService
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

        foreach ($catalog as $key => $addon) {
            if (! ($addon['enabled'] ?? true)) {
                $synced[$key] = $addon;

                continue;
            }

            $synced[$key] = $this->syncAddon($key, $addon, $currency, $indiaCurrency);
        }

        return $synced;
    }

    private function syncAddon(string $key, array $addon, string $currency, ?string $indiaCurrency): array
    {
        $monthlyPlanId = $this->resolvePlanId(
            $key,
            $addon,
            AddonCatalogDefinition::priceForRegion($addon, false) * 100,
            $currency,
            'razorpay_plan_id_monthly',
        );

        $addon = array_merge($addon, [
            'razorpay_plan_id_monthly' => $monthlyPlanId,
        ]);

        if ($indiaCurrency !== null && strtoupper($indiaCurrency) !== strtoupper($currency)) {
            $addon = array_merge($addon, [
                'razorpay_plan_id_monthly_india' => $this->resolvePlanId(
                    $key,
                    $addon,
                    AddonCatalogDefinition::priceForRegion($addon, true) * 100,
                    $indiaCurrency,
                    'razorpay_plan_id_monthly_india',
                ),
            ]);
        }

        return $addon;
    }

    private function resolvePlanId(
        string $key,
        array $addon,
        int $amountMinor,
        string $currency,
        string $primaryKey,
    ): ?string {
        if ($amountMinor <= 0) {
            return null;
        }

        $existingPlanId = $addon[$primaryKey] ?? null;

        try {
            if (is_string($existingPlanId) && $existingPlanId !== '') {
                $existingPlan = $this->razorpayCatalog->retrievePlan($existingPlanId);

                if ($this->razorpayCatalog->planMatches($existingPlan, $amountMinor, $currency, 'month')) {
                    return $existingPlanId;
                }
            }

            $created = $this->razorpayCatalog->createAddonPlan(
                (string) ($addon['name'] ?? ucfirst($key)),
                $amountMinor,
                $currency,
                $key,
            );

            return (string) ($created['id'] ?? '') ?: null;
        } catch (Error $exception) {
            Log::warning('Razorpay add-on sync skipped for unsupported currency or API error', [
                'addon' => $key,
                'currency' => $currency,
                'message' => $exception->getMessage(),
            ]);

            $this->skipped[] = sprintf('%s add-on (%s)', $key, strtoupper($currency));

            return is_string($existingPlanId) && $existingPlanId !== '' ? $existingPlanId : null;
        }
    }
}
