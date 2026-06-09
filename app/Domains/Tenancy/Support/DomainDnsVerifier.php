<?php

namespace App\Domains\Tenancy\Support;

class DomainDnsVerifier
{
    public function hasTxtRecord(string $host, string $expectedValue): bool
    {
        $records = @dns_get_record($host, DNS_TXT) ?: [];

        foreach ($records as $record) {
            $txt = (string) ($record['txt'] ?? '');

            if ($txt === $expectedValue || str_contains($txt, $expectedValue)) {
                return true;
            }
        }

        return false;
    }
}
