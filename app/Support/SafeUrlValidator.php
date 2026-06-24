<?php

namespace App\Support;

use InvalidArgumentException;

class SafeUrlValidator
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
    ];

    public static function assertPublicHttpUrl(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new InvalidArgumentException('Invalid webhook URL.');
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Webhook URL must use HTTP or HTTPS.');
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));

        if ($host === '') {
            throw new InvalidArgumentException('Webhook URL must include a host.');
        }

        if (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('Webhook URL must not target internal hosts.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            self::assertPublicIp($host);

            return;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            $resolved = gethostbyname($host);

            if ($resolved === $host) {
                throw new InvalidArgumentException('Webhook URL host could not be resolved.');
            }

            self::assertPublicIp($resolved);

            return;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip) && $ip !== '') {
                self::assertPublicIp($ip);
            }
        }
    }

    private static function assertPublicIp(string $ip): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('Webhook URL must not target private or reserved addresses.');
        }
    }
}
