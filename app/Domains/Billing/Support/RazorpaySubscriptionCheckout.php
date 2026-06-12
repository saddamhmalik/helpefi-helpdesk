<?php

namespace App\Domains\Billing\Support;

final class RazorpaySubscriptionCheckout
{
    public static function hostedPageUrl(array $entity): ?string
    {
        if (($entity['status'] ?? '') !== 'created') {
            return null;
        }

        $expireBy = $entity['expire_by'] ?? null;

        if (is_numeric($expireBy) && now()->getTimestamp() > (int) $expireBy) {
            return null;
        }

        $shortUrl = $entity['short_url'] ?? null;

        if (! is_string($shortUrl) || $shortUrl === '') {
            return null;
        }

        return $shortUrl;
    }

    public static function shouldResetIncompleteSubscription(array $entity): bool
    {
        $status = (string) ($entity['status'] ?? '');

        if ($status === 'created') {
            return self::hostedPageUrl($entity) === null;
        }

        return in_array($status, ['authenticated', 'pending', 'halted', 'expired'], true);
    }
}
