<?php

namespace App\Domains\Tenancy\Support;

class CurrencyCatalog
{
    public static function all(): array
    {
        return config('currencies', []);
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function isSupported(string $code): bool
    {
        return array_key_exists(strtoupper($code), self::all());
    }

    public static function normalize(string $code): string
    {
        $code = strtoupper(trim($code));

        return self::isSupported($code) ? $code : strtoupper((string) config('billing.currency', 'USD'));
    }

    public static function meta(string $code): array
    {
        $code = self::normalize($code);
        $entry = self::all()[$code];

        return [
            'code' => $code,
            'symbol' => $entry['symbol'],
            'name' => $entry['name'],
        ];
    }

    public static function forSelect(): array
    {
        return collect(self::all())
            ->map(fn (array $entry, string $code) => [
                'code' => $code,
                'symbol' => $entry['symbol'],
                'name' => $entry['name'],
                'label' => "{$code} ({$entry['symbol']}) — {$entry['name']}",
            ])
            ->values()
            ->all();
    }
}
