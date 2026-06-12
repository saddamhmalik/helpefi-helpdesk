<?php

namespace App\Support;

class AppearanceSupport
{
    public const MODES = ['light', 'dark', 'system'];

    public const DEFAULT = 'system';

    public static function resolve(?string $appearance): string
    {
        $normalized = strtolower(trim((string) $appearance));

        return in_array($normalized, self::MODES, true) ? $normalized : self::DEFAULT;
    }

    public static function options(): array
    {
        return collect(self::MODES)
            ->map(fn (string $value) => ['value' => $value])
            ->all();
    }
}
