<?php

namespace App\Domains\Tenancy\Support;

class DomainDnsVerifier
{
    public function hasTxtRecord(string $host, string $expectedValue): bool
    {
        foreach ($this->txtRecordsFor($host) as $txt) {
            if ($txt === $expectedValue || str_contains($txt, $expectedValue)) {
                return true;
            }
        }

        return false;
    }

    public function txtRecordsFor(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT) ?: [];
        $values = [];

        foreach ($records as $record) {
            $txt = (string) ($record['txt'] ?? '');

            if ($txt !== '') {
                $values[] = $txt;
            }
        }

        return $values;
    }
}
