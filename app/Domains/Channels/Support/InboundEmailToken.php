<?php

namespace App\Domains\Channels\Support;

use RuntimeException;

class InboundEmailToken
{
    public static function resolve(): string
    {
        $token = (string) (config('helpdesk.inbound_email_token') ?? '');

        if ($token === '' || $token === 'dev-inbound-token') {
            if (app()->environment('production')) {
                throw new RuntimeException('INBOUND_EMAIL_TOKEN must be set to a strong random value in production.');
            }

            return $token !== '' ? $token : 'dev-inbound-token';
        }

        if (strlen($token) < 32 && app()->environment('production')) {
            throw new RuntimeException('INBOUND_EMAIL_TOKEN must be at least 32 characters in production.');
        }

        return $token;
    }
}
