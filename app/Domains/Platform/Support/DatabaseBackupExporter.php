<?php

namespace App\Domains\Platform\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class DatabaseBackupExporter
{
    public function exportConnection(string $connectionName, string $targetPath): array
    {
        $config = config("database.connections.{$connectionName}");
        $driver = $config['driver'] ?? null;

        if ($driver === 'sqlite') {
            return $this->exportSqlite($connectionName, $config['database'], $targetPath);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->exportMysql($config, $targetPath);
        }

        throw new RuntimeException("Unsupported database driver [{$driver}] for backups.");
    }

    public function exportDefaultConnection(string $targetPath): array
    {
        return $this->exportConnection(config('database.default'), $targetPath);
    }

    private function exportSqlite(string $connectionName, string $databasePath, string $targetPath): array
    {
        if ($databasePath !== ':memory:' && is_file($databasePath)) {
            if (! copy($databasePath, $targetPath)) {
                throw new RuntimeException('Failed to copy SQLite database file.');
            }

            return $this->fileMeta($targetPath);
        }

        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdo = DB::connection($connectionName)->getPdo();

        try {
            $pdo->exec("VACUUM INTO '".str_replace("'", "''", $targetPath)."'");
        } catch (\Throwable) {
            $this->exportSqliteTables($connectionName, $targetPath);
        }

        if (! is_file($targetPath)) {
            throw new RuntimeException('SQLite backup file was not created.');
        }

        return $this->fileMeta($targetPath);
    }

    private function exportSqliteTables(string $connectionName, string $targetPath): void
    {
        $handle = fopen($targetPath, 'w');
        $connection = DB::connection($connectionName);
        $tables = collect($connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name');

        foreach ($tables as $table) {
            $create = $connection->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

            if (! $create?->sql) {
                continue;
            }

            fwrite($handle, $create->sql.";\n");

            $rows = $connection->table($table)->get();

            foreach ($rows as $row) {
                $values = collect((array) $row)
                    ->map(fn ($value) => match (true) {
                        $value === null => 'NULL',
                        is_numeric($value) => $value,
                        default => "'".str_replace("'", "''", (string) $value)."'",
                    })
                    ->implode(', ');

                fwrite($handle, "INSERT INTO {$table} VALUES ({$values});\n");
            }
        }

        fclose($handle);
    }

    private function exportMysql(array $config, string $targetPath): array
    {
        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $mysqldump = DatabaseMysqlCli::binary('mysqldump');

        $command = [
            $mysqldump,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$targetPath,
            $config['database'],
        ];

        $result = Process::env(DatabaseMysqlCli::passwordEnvironment($config))
            ->timeout(DatabaseMysqlCli::processTimeout())
            ->run($command);

        if (! $result->successful() || ! is_file($targetPath)) {
            $message = trim($result->errorOutput() ?: $result->output() ?: 'mysqldump failed to create backup file.');

            if (str_contains($message, 'not found')) {
                $message .= ' Install the MySQL client tools (mysqldump) in the application container.';
            }

            throw new RuntimeException($message);
        }

        return $this->fileMeta($targetPath);
    }

    private function fileMeta(string $path): array
    {
        return [
            'size' => filesize($path) ?: 0,
            'checksum' => hash_file('sha256', $path) ?: null,
        ];
    }
}
