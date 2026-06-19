<?php

namespace App\Domains\Tenancy\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExternalTenantDatabaseTester
{
    public function __construct(
        private ExternalTenantDatabaseService $database,
    ) {
    }

    public function testConnection(array $config): ?string
    {
        return $this->ping($this->database->buildConnectionConfig($config), 'Database connection failed');
    }

    public function testReadOnlyConnection(array $config): ?string
    {
        if (! filled($config['read_only_username'] ?? null)) {
            return null;
        }

        $readOnlyConfig = $this->database->buildConnectionConfig([
            ...$config,
            'username' => $config['read_only_username'],
            'password' => $config['read_only_password'] ?? '',
        ]);

        return $this->ping($readOnlyConfig, 'Read-only database connection failed');
    }

    private function ping(array $connectionConfig, string $prefix): ?string
    {
        $connectionName = 'tenant_infrastructure_test';
        $timeout = max(1, (int) config('tenant_infrastructure.connection_timeout_seconds', 10));
        $previousSocketTimeout = ini_get('default_socket_timeout');

        try {
            ini_set('default_socket_timeout', (string) $timeout);
            Config::set("database.connections.{$connectionName}", $connectionConfig);
            DB::purge($connectionName);
            DB::connection($connectionName)->select('select 1 as ping');

            $writeError = $this->verifyWriteAccess($connectionName);

            if ($writeError !== null) {
                return $prefix.': '.$writeError;
            }

            DB::purge($connectionName);
            Config::offsetUnset("database.connections.{$connectionName}");

            return null;
        } catch (Throwable $exception) {
            DB::purge($connectionName);
            Config::offsetUnset("database.connections.{$connectionName}");

            return $prefix.': '.$this->formatConnectionError($exception->getMessage());
        } finally {
            if ($previousSocketTimeout !== false) {
                ini_set('default_socket_timeout', (string) $previousSocketTimeout);
            }
        }
    }

    private function formatConnectionError(string $message): string
    {
        $normalized = strtolower($message);

        if (
            str_contains($normalized, 'timed out')
            || str_contains($normalized, 'time out')
            || str_contains($normalized, 'connection refused')
            || str_contains($normalized, 'no route to host')
            || str_contains($normalized, 'network is unreachable')
        ) {
            $egressIps = config('tenant_infrastructure.egress_ips', []);
            $hint = $egressIps !== []
                ? ' Allow inbound MySQL (3306) from helpefi egress IP(s): '.implode(', ', $egressIps).'.'
                : ' Allow inbound MySQL (3306) from the helpefi server IP in your RDS security group.';

            return $message.$hint;
        }

        return $message;
    }

    private function verifyWriteAccess(string $connectionName): ?string
    {
        $table = '__helpefi_conn_test_'.bin2hex(random_bytes(4));

        try {
            DB::connection($connectionName)->statement(
                'CREATE TABLE `'.$table.'` (`id` TINYINT UNSIGNED NOT NULL PRIMARY KEY)'
            );
            DB::connection($connectionName)->statement('DROP TABLE `'.$table.'`');

            return null;
        } catch (Throwable $exception) {
            return $this->formatConnectionError($exception->getMessage());
        }
    }
}
