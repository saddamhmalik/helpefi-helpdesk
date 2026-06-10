<?php

namespace App\Domains\Channels\Support;

class EmailPlaceholders
{
    public static function render(string $content, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }
}
