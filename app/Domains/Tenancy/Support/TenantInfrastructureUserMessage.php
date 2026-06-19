<?php

namespace App\Domains\Tenancy\Support;

final class TenantInfrastructureUserMessage
{
    public static function sanitize(?string $message): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        $normalized = trim($message);

        if (preg_match('/^(Database|Storage) migration failed:\s*(.*)$/si', $normalized, $matches)) {
            $context = strtolower($matches[1]) === 'database' ? 'database' : 'storage';

            return self::migrationFailed($context, $matches[2]);
        }

        if (preg_match('/^Tenant migration failed:\s*(.*)$/si', $normalized, $matches)) {
            return self::migrationFailed('database', $matches[1]);
        }

        if (self::isTechnical($normalized)) {
            return self::migrationFailed(self::detectContext($normalized), $normalized);
        }

        return $normalized;
    }

    public static function migrationFailed(string $context, ?string $technicalMessage): string
    {
        $prefix = match ($context) {
            'database' => 'Database migration failed: ',
            'storage' => 'Storage migration failed: ',
            default => 'Backup export failed: ',
        };

        return $prefix.self::sanitizeDetail($technicalMessage ?? 'Job failed.', $context);
    }

    public static function backupExportFailed(?string $technicalMessage): string
    {
        return self::migrationFailed('backup', $technicalMessage);
    }

    private static function sanitizeDetail(string $detail, string $context): string
    {
        $detail = trim($detail);
        $lower = strtolower($detail);

        if (str_contains($lower, 'mysqldump') && str_contains($lower, 'not found')) {
            return 'Database export is not available on this server yet. Contact support or try again after your workspace is updated.';
        }

        if (str_contains($lower, 'attempted too many times')) {
            return 'Could not be completed after multiple attempts. Retry migration or contact support.';
        }

        if (str_contains($lower, 'already exists')) {
            return 'Workspace tables are already present in this database.';
        }

        if ($detail === '' || self::isTechnical($detail)) {
            return self::genericFailure($context);
        }

        if (str_contains($lower, 'exceeded the timeout') || str_contains($lower, 'timed out')) {
            return 'Timed out. Retry after confirming credentials and network access.';
        }

        if (str_contains($lower, 'access denied')) {
            return $context === 'database'
                ? 'Database credentials or permissions are incorrect. Confirm the user can create tables in your application database.'
                : 'Storage credentials or permissions are incorrect. Confirm the bucket is writable.';
        }

        if (
            str_contains($lower, 'connection timed out')
            || str_contains($lower, 'connection refused')
            || str_contains($lower, 'no route to host')
        ) {
            return $context === 'database'
                ? 'Could not reach the database host. Check firewall rules and allow helpefi egress IPs.'
                : 'Could not reach the storage endpoint. Check credentials, bucket, and network access.';
        }

        if (strlen($detail) > 200) {
            return self::genericFailure($context);
        }

        return $detail;
    }

    private static function detectContext(string $message): string
    {
        return str_contains(strtolower($message), 'storage migration') ? 'storage' : 'database';
    }

    private static function genericFailure(string $context): string
    {
        return match ($context) {
            'backup' => 'Could not export the database backup. Retry or contact support.',
            default => 'Could not be completed. Retry migration or contact support.',
        };
    }

    private static function isTechnical(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($message, '\\')
            || str_contains($message, ' at /var/')
            || str_contains($message, 'vendor/laravel')
            || str_contains($message, 'stacktrace')
            || str_contains($message, 'SQLSTATE')
            || str_contains($lower, 'connection: central')
            || str_contains($lower, 'create table');
    }
}
