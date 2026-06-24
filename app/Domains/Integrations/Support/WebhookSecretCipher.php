<?php

namespace App\Domains\Integrations\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class WebhookSecretCipher
{
    public static function encrypt(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public static function decrypt(string $value): string
    {
        if (! self::looksEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            throw new RuntimeException('Webhook secret could not be decrypted.');
        }
    }

    public static function looksEncrypted(string $value): bool
    {
        return str_starts_with($value, 'eyJpdiI6');
    }
}
