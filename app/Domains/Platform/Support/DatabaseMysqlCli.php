<?php

namespace App\Domains\Platform\Support;

class DatabaseMysqlCli
{
    public static function available(string $name = 'mysqldump'): bool
    {
        return self::resolvePath($name) !== null;
    }

    private static function resolvePath(string $name): ?string
    {
        $configured = config('tenant_infrastructure.mysql_client_binary_path');

        if (is_string($configured) && $configured !== '') {
            $path = rtrim($configured, '/').'/'.$name;

            return is_executable($path) ? $path : null;
        }

        foreach (['/usr/bin/'.$name, '/usr/local/bin/'.$name] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $which = trim((string) shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));

        return $which !== '' && is_executable($which) ? $which : null;
    }

    public static function binary(string $name): string
    {
        return self::resolvePath($name) ?? $name;
    }

    public static function processTimeout(): int
    {
        return max(60, (int) config('tenant_infrastructure.database_migration_timeout_seconds', 3600));
    }

    public static function passwordEnvironment(array $config): array
    {
        if (empty($config['password'])) {
            return [];
        }

        return ['MYSQL_PWD' => $config['password']];
    }
}
