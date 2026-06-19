<?php

namespace App\Domains\Platform\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class DatabaseBackupImporter
{
    public function importMysql(array $config, string $sourcePath): void
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Database import file was not found.');
        }

        $command = [
            DatabaseMysqlCli::binary('mysql'),
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? ''),
            $config['database'] ?? '',
        ];

        $handle = fopen($sourcePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Database import file could not be opened.');
        }

        try {
            $result = Process::env(DatabaseMysqlCli::passwordEnvironment($config))
                ->timeout(DatabaseMysqlCli::processTimeout())
                ->input($handle)
                ->run($command);
        } finally {
            fclose($handle);
        }

        if (! $result->successful()) {
            $message = trim($result->errorOutput() ?: $result->output() ?: 'mysql import failed.');

            if (str_contains($message, 'not found')) {
                $message .= ' Install the MySQL client tools (mysql) in the application container.';
            }

            throw new RuntimeException($message);
        }
    }
}
