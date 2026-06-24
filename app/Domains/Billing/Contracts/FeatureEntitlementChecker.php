<?php

namespace App\Domains\Billing\Contracts;

interface FeatureEntitlementChecker
{
    public function canUseFeature(string $feature): bool;

    public function withinLimit(string $key, int $buffer = 0): bool;

    public function assertFeature(string $feature): void;

    public function assertLimit(string $key, int $buffer = 0): void;

    public function hasAddon(string $addonKey): bool;
}
