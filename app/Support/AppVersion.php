<?php

namespace App\Support;

class AppVersion
{
    public static function current(): string
    {
        $env = trim((string) config('app.version'));

        if ($env !== '') {
            return $env;
        }

        $file = base_path('VERSION');

        if (is_readable($file)) {
            $version = trim((string) file_get_contents($file));

            if ($version !== '') {
                return $version;
            }
        }

        return 'dev';
    }
}
