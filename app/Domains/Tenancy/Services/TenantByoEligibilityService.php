<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\AddonCatalogDefinition;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class TenantByoEligibilityService
{
    public function assess(Tenant $tenant, bool $includeLegacyAllowlist = true): array
    {
        $tenant->loadMissing('subscription');

        $databaseEligible = $this->canConfigureDatabase($tenant, $includeLegacyAllowlist);
        $storageEligible = $this->canConfigureStorage($tenant, $includeLegacyAllowlist);
        $reasons = $this->collectReasons($tenant, $databaseEligible, $storageEligible);

        return [
            'eligible' => $databaseEligible || $storageEligible,
            'database_eligible' => $databaseEligible,
            'storage_eligible' => $storageEligible,
            'byo_allowed' => (bool) $tenant->byo_allowed,
            'has_byo_database_addon' => $this->hasAddonFeature($tenant, 'byo_database'),
            'has_byo_storage_addon' => $this->hasAddonFeature($tenant, 'byo_storage'),
            'on_trial' => $tenant->subscription?->isOnTrial() ?? false,
            'enterprise_plan' => $this->hasEnterprisePlan($tenant),
            'reasons' => $reasons,
        ];
    }

    public function isEligible(Tenant $tenant): bool
    {
        return $this->assess($tenant)['eligible'];
    }

    public function canConfigureDatabase(Tenant $tenant, bool $includeLegacyAllowlist = true): bool
    {
        if (! $this->isByoEnabled()) {
            return false;
        }

        if ($this->isSelfHosted()) {
            return ! ($tenant->subscription?->isOnTrial() ?? false);
        }

        if ($tenant->subscription?->isOnTrial()) {
            return false;
        }

        if ($this->hasAddonFeature($tenant, 'byo_database')) {
            return true;
        }

        if ($includeLegacyAllowlist && $this->legacyAllowlistEligible($tenant)) {
            return true;
        }

        return false;
    }

    public function canConfigureStorage(Tenant $tenant, bool $includeLegacyAllowlist = true): bool
    {
        if (! $this->isByoEnabled()) {
            return false;
        }

        if ($this->isSelfHosted()) {
            return ! ($tenant->subscription?->isOnTrial() ?? false);
        }

        if ($tenant->subscription?->isOnTrial()) {
            return false;
        }

        if ($this->hasAddonFeature($tenant, 'byo_storage')) {
            return true;
        }

        if ($includeLegacyAllowlist && $this->legacyAllowlistEligible($tenant)) {
            return true;
        }

        return false;
    }

    public function assertEligible(Tenant $tenant): void
    {
        if ($this->isEligible($tenant)) {
            return;
        }

        throw ValidationException::withMessages([
            'infrastructure' => implode(' ', $this->assess($tenant)['reasons']),
        ]);
    }

    public function assertCanConfigureDatabase(Tenant $tenant, bool $includeLegacyAllowlist = true): void
    {
        if ($this->canConfigureDatabase($tenant, $includeLegacyAllowlist)) {
            return;
        }

        throw ValidationException::withMessages([
            'database_mode' => $this->databaseIneligibilityMessage($tenant),
        ]);
    }

    public function assertCanConfigureStorage(Tenant $tenant, bool $includeLegacyAllowlist = true): void
    {
        if ($this->canConfigureStorage($tenant, $includeLegacyAllowlist)) {
            return;
        }

        throw ValidationException::withMessages([
            'storage_mode' => $this->storageIneligibilityMessage($tenant),
        ]);
    }

    private function collectReasons(Tenant $tenant, bool $databaseEligible, bool $storageEligible): array
    {
        if ($databaseEligible && $storageEligible) {
            return [];
        }

        $reasons = [];

        if (! $this->isByoEnabled()) {
            $reasons[] = 'Bring-your-own infrastructure is not enabled on this platform.';
        }

        if ($tenant->subscription?->isOnTrial()) {
            $reasons[] = 'Trial workspaces cannot use bring-your-own infrastructure.';
        }

        if (! $databaseEligible && ! $storageEligible) {
            if (! $this->hasAddonFeature($tenant, 'byo_database') && ! $this->hasAddonFeature($tenant, 'byo_storage') && ! $this->legacyAllowlistEligible($tenant)) {
                $reasons[] = 'Purchase the Bring Your Own Database or Bring Your Own Storage add-on from billing settings, or contact support for enterprise allowlisting.';
            } elseif (! $databaseEligible) {
                $reasons[] = $this->databaseIneligibilityMessage($tenant);
            } elseif (! $storageEligible) {
                $reasons[] = $this->storageIneligibilityMessage($tenant);
            }
        }

        return array_values(array_unique($reasons));
    }

    private function databaseIneligibilityMessage(Tenant $tenant): string
    {
        if ($tenant->subscription?->isOnTrial()) {
            return 'Trial workspaces cannot configure an external database.';
        }

        if ($this->hasAddonFeature($tenant, 'byo_database') || $this->legacyAllowlistEligible($tenant)) {
            return 'External database configuration is not available for this workspace.';
        }

        return 'Purchase the Bring Your Own Database add-on to connect your own MySQL database.';
    }

    private function storageIneligibilityMessage(Tenant $tenant): string
    {
        if ($tenant->subscription?->isOnTrial()) {
            return 'Trial workspaces cannot configure external storage.';
        }

        if ($this->hasAddonFeature($tenant, 'byo_storage') || $this->legacyAllowlistEligible($tenant)) {
            return 'External storage configuration is not available for this workspace.';
        }

        return 'Purchase the Bring Your Own Storage add-on to connect your own S3 or R2 bucket.';
    }

    private function legacyAllowlistEligible(Tenant $tenant): bool
    {
        if (! (bool) $tenant->byo_allowed) {
            return false;
        }

        if ($this->requiresEnterprisePlan() && ! $this->hasEnterprisePlan($tenant)) {
            return false;
        }

        return true;
    }

    private function hasAddonFeature(Tenant $tenant, string $feature): bool
    {
        $subscription = $tenant->subscription;

        if ($subscription === null || ! $subscription->isAccessible()) {
            return false;
        }

        foreach ($subscription->active_addons ?? [] as $addonKey) {
            if (AddonCatalogDefinition::featureForAddon($addonKey) === $feature) {
                return true;
            }
        }

        return false;
    }

    private function isSelfHosted(): bool
    {
        return config('deployment.mode') === 'self_hosted';
    }

    private function isByoEnabled(): bool
    {
        return (bool) config('tenant_infrastructure.enabled');
    }

    private function requiresEnterprisePlan(): bool
    {
        return (bool) config('tenant_infrastructure.requires_enterprise_plan', true);
    }

    private function hasEnterprisePlan(Tenant $tenant): bool
    {
        return $tenant->subscription?->plan === 'enterprise';
    }
}
