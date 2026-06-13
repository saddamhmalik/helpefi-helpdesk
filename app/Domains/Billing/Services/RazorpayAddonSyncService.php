<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Repositories\RazorpayCatalogRepository;
use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Errors\Error;

class RazorpayAddonSyncService
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

        foreach ($catalog as $key => $addon) {
            if (! ($addon['enabled'] ?? true)) {
                $synced[$key] = $addon;

                continue;
            }

            $synced[$key] = $this->syncAddon($key, $addon, $currency);
        }

        return $synced;
    }

    private function syncAddon(string $key, array $addon, string $currency): array
    {
        $amountMinor = max(0, (int) ($addon['price_monthly'] ?? 0)) * 100;
        $existingPlanId = AddonCatalogDefinition::razorpayPlanId($addon);

        if ($amountMinor <= 0) {
            return $addon;
        }

        try {
            if (is_string($existingPlanId) && $existingPlanId !== '') {
                $existingPlan = $this->razorpayCatalog->retrievePlan($existingPlanId);

                if ($this->razorpayCatalog->planMatches($existingPlan, $amountMinor, $currency, 'month')) {
                    return array_merge($addon, [
                        'razorpay_plan_id_monthly' => $existingPlanId,
                    ]);
                }
            }

            $created = $this->razorpayCatalog->createAddonPlan(
                (string) ($addon['name'] ?? ucfirst($key)),
                $amountMinor,
                $currency,
                $key,
            );

            return array_merge($addon, [
                'razorpay_plan_id_monthly' => (string) ($created['id'] ?? '') ?: null,
            ]);
        } catch (Error $exception) {
            Log::warning('Razorpay add-on sync skipped for unsupported currency or API error', [
                'addon' => $key,
                'currency' => $currency,
                'message' => $exception->getMessage(),
            ]);

            return array_merge($addon, [
                'razorpay_plan_id_monthly' => null,
            ]);
        }
    }
}
