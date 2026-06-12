<?php

namespace App\Domains\Billing\Support;

final class RazorpaySubscriptionCheckout
{
    public static function canAuthenticateViaStandardCheckout(array $entity): bool
    {
        if (($entity['status'] ?? '') !== 'created') {
            return false;
        }

        if (self::isExpired($entity)) {
            return false;
        }

        $subscriptionId = $entity['id'] ?? null;

        return is_string($subscriptionId) && $subscriptionId !== '';
    }

    public static function isExpired(array $entity): bool
    {
        $expireBy = $entity['expire_by'] ?? null;

        return is_numeric($expireBy) && now()->getTimestamp() > (int) $expireBy;
    }

    public static function shouldResetIncompleteSubscription(array $entity): bool
    {
        $status = (string) ($entity['status'] ?? '');

        if ($status === 'created') {
            return ! self::canAuthenticateViaStandardCheckout($entity);
        }

        return in_array($status, ['authenticated', 'pending', 'halted', 'expired'], true);
    }
}
