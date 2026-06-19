<?php

namespace App\Support;

use App\Domains\Platform\Services\PlatformSlowQueryService;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class SlowQueryLogger
{
    public static function register(): void
    {
        if (app()->runningUnitTests() || ! config('database.slow_query.enabled')) {
            return;
        }

        $threshold = max(1, (int) config('database.slow_query.threshold_ms', 500));

        DB::listen(function (QueryExecuted $event) use ($threshold) {
            if (self::shouldIgnore($event)) {
                return;
            }

            $effectiveThreshold = self::effectiveThreshold($threshold, $event->connection);

            if ($event->time < $effectiveThreshold) {
                return;
            }

            self::log($event);
        });
    }

    private static function shouldIgnore(QueryExecuted $event): bool
    {
        if (self::shouldIgnoreSql($event->sql)) {
            return true;
        }

        $connectionName = $event->connection->getName();

        if (in_array($connectionName, config('database.slow_query.ignore_connections', []), true)) {
            return true;
        }

        return false;
    }

    private static function effectiveThreshold(int $defaultThreshold, Connection $connection): int
    {
        $remoteThreshold = (int) config('database.slow_query.remote_threshold_ms', 0);

        if ($remoteThreshold <= 0 || ! self::isRemoteConnection($connection)) {
            return $defaultThreshold;
        }

        return max($defaultThreshold, $remoteThreshold);
    }

    private static function isRemoteConnection(Connection $connection): bool
    {
        $host = (string) ($connection->getConfig()['host'] ?? '');
        $centralHost = (string) config('database.connections.'.config('tenancy.database.central_connection', 'central').'.host');

        if ($host === '' || $host === $centralHost || in_array($host, ['127.0.0.1', 'localhost', 'mysql'], true)) {
            return false;
        }

        return true;
    }

    private static function shouldIgnoreSql(string $sql): bool
    {
        foreach (config('database.slow_query.ignore_patterns', []) as $pattern) {
            if (@preg_match($pattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function log(QueryExecuted $event): void
    {
        $connection = $event->connection;
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? tenant('id') : null;
        $request = request();
        $bindings = config('database.slow_query.log_bindings') ? $event->bindings : null;
        $source = self::resolveSource();
        $database = self::connectionContext($connection);
        $routeName = $request?->route()?->getName();
        $logSql = self::truncateSql($event->sql);

        if (config('database.slow_query.log')) {
            logger()->channel(config('database.slow_query.channel'))->warning('Slow query detected', [
                'connection' => $connection->getName(),
                'database_host' => $database['database_host'],
                'database_name' => $database['database_name'],
                'sql' => $logSql,
                'time_ms' => $event->time,
                'tenant_id' => $tenantId,
                'route' => $routeName,
                'source_file' => $source['source_file'],
                'source_line' => $source['source_line'],
                'source_callable' => $source['source_callable'],
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'bindings' => $bindings,
            ]);
        }

        app(PlatformSlowQueryService::class)->record(
            connection: $connection->getName(),
            sql: $event->sql,
            timeMs: $event->time,
            tenantId: $tenantId,
            bindings: $bindings,
            method: $request?->method(),
            url: $request?->fullUrl(),
            routeName: $routeName,
            sourceFile: $source['source_file'],
            sourceLine: $source['source_line'],
            sourceCallable: $source['source_callable'],
            databaseHost: $database['database_host'],
            databaseName: $database['database_name'],
        );
    }

    private static function connectionContext(Connection $connection): array
    {
        $config = $connection->getConfig();

        return [
            'database_host' => isset($config['host']) ? (string) $config['host'] : null,
            'database_name' => isset($config['database']) ? (string) $config['database'] : null,
        ];
    }

    private static function resolveSource(): array
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25) as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null
                || str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)
                || str_contains($file, 'SlowQueryLogger.php')) {
                continue;
            }

            $relative = str_starts_with($file, $base)
                ? substr($file, strlen($base))
                : $file;

            $callable = null;

            if (isset($frame['class'], $frame['function'])) {
                $callable = $frame['class'].'@'.$frame['function'];
            } elseif (isset($frame['function'])) {
                $callable = $frame['function'];
            }

            return [
                'source_file' => $relative,
                'source_line' => isset($frame['line']) ? (int) $frame['line'] : null,
                'source_callable' => $callable,
            ];
        }

        return [
            'source_file' => null,
            'source_line' => null,
            'source_callable' => null,
        ];
    }

    private static function truncateSql(string $sql): string
    {
        $limit = max(120, (int) config('database.slow_query.log_sql_limit', 500));

        if (mb_strlen($sql) <= $limit) {
            return $sql;
        }

        return mb_substr($sql, 0, $limit).'…';
    }
}
