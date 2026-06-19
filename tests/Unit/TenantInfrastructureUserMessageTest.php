<?php

namespace Tests\Unit;

use App\Domains\Tenancy\Support\TenantInfrastructureUserMessage;
use PHPUnit\Framework\TestCase;

class TenantInfrastructureUserMessageTest extends TestCase
{
    public function test_sanitizes_job_class_names_from_migration_failures(): void
    {
        $message = 'Database migration failed: App\Domains\Tenancy\Jobs\MigrateManagedToExternalDatabaseJob has been attempted too many times.';

        $this->assertSame(
            'Database migration failed: Could not be completed after multiple attempts. Retry migration or contact support.',
            TenantInfrastructureUserMessage::sanitize($message),
        );
    }

    public function test_sanitizes_tenant_migration_sql_errors(): void
    {
        $message = "Tenant migration failed: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'users' already exists (Connection: central, Host: mysql, Port: 3306, Database: helpdesk_central, SQL: create table `users` ...)";

        $this->assertSame(
            'Database migration failed: Workspace tables are already present in this database.',
            TenantInfrastructureUserMessage::sanitize($message),
        );
    }

    public function test_sanitizes_timeout_messages(): void
    {
        $message = 'Database migration failed: The process exceeded the timeout of 60 seconds.';

        $this->assertSame(
            'Database migration failed: Timed out. Retry after confirming credentials and network access.',
            TenantInfrastructureUserMessage::sanitize($message),
        );
    }

    public function test_preserves_user_friendly_messages(): void
    {
        $message = 'Database migration timed out. Retry after confirming your credentials.';

        $this->assertSame($message, TenantInfrastructureUserMessage::sanitize($message));
    }

    public function test_sanitizes_missing_mysqldump_for_backup_export(): void
    {
        $message = 'sh: 1: exec: mysqldump: not found Install the MySQL client tools (mysqldump) in the application container.';

        $this->assertSame(
            'Backup export failed: Database export is not available on this server yet. Contact support or try again after your workspace is updated.',
            TenantInfrastructureUserMessage::backupExportFailed($message),
        );
    }
}
