<?php

namespace App\Domains\Contacts\Support;

use Illuminate\Validation\ValidationException;

class OrganizationDomainRules
{
    private const BLOCKED_DOMAINS = [
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'yahoo.co.uk',
        'hotmail.com',
        'outlook.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'aol.com',
        'proton.me',
        'protonmail.com',
        'zoho.com',
        'yandex.com',
        'gmx.com',
        'mail.com',
        'fastmail.com',
    ];

    public static function normalize(string $domain): string
    {
        return strtolower(trim($domain));
    }

    public static function assertValid(string $domain): string
    {
        $domain = self::normalize($domain);

        if ($domain === '' || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
            throw ValidationException::withMessages([
                'domains' => 'Enter a valid organization domain.',
            ]);
        }

        if (in_array($domain, self::BLOCKED_DOMAINS, true)) {
            throw ValidationException::withMessages([
                'domains' => "Public email domain {$domain} cannot be linked to an organization.",
            ]);
        }

        return $domain;
    }

    public static function normalizeMany(array $domains): array
    {
        return array_values(array_unique(array_map(
            fn (string $domain) => self::assertValid($domain),
            array_filter($domains),
        )));
    }
}
