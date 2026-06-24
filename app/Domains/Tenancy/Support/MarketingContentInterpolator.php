<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Tenancy\Services\CentralSettingsService;

class MarketingContentInterpolator
{
    public function __construct(private array $extra = [])
    {
    }

    public function with(array $extra): self
    {
        return new self(array_merge($this->extra, $extra));
    }

    public function interpolate(mixed $value, array $extra = []): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->interpolate($item, $extra), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = str_replace("{'@'}", '@', $value);

        foreach ($this->replacements($extra) as $search => $replace) {
            $value = str_replace($search, $replace, $value);
        }

        return $value;
    }

    private function replacements(array $extra): array
    {
        $trialDays = (string) app(CentralSettingsService::class)->trialDays();
        $brand = config('app.name', 'helpefi');

        $pairs = array_merge([
            'brand' => $brand,
            'days' => $trialDays,
            'trialDays' => $trialDays,
        ], $extra);

        $replacements = [];

        foreach ($pairs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $replacements['{'.$key.'}'] = (string) $value;
        }

        return $replacements;
    }
}
