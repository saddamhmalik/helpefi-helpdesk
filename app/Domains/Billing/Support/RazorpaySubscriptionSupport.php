<?php

namespace App\Domains\Billing\Support;

final class RazorpaySubscriptionSupport
{
    public static function normalizePayload(array $entity): object
    {
        return (object) $entity;
    }

    public static function isAddonSubscription(array $entity): bool
    {
        $notes = $entity['notes'] ?? [];

        return ($notes['billing_type'] ?? null) === 'addon'
            || isset($notes['addon_key']);
    }
}
