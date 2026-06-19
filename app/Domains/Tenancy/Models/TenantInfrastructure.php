<?php

namespace App\Domains\Tenancy\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInfrastructure extends Model
{
    public const MODE_MANAGED = 'managed';

    public const MODE_EXTERNAL = 'external';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const MIGRATION_QUEUED = 'queued';

    public const MIGRATION_RUNNING = 'running';

    public const MIGRATION_COMPLETED = 'completed';

    public const MIGRATION_FAILED = 'failed';

    protected $connection = 'central';

    protected $table = 'tenant_infrastructure';

    protected $fillable = [
        'tenant_id',
        'database_mode',
        'database_config',
        'database_migration_status',
        'storage_mode',
        'storage_config',
        'storage_migration_status',
        'backup_export_status',
        'backup_export_path',
        'backup_export_message',
        'auto_backup_enabled',
        'auto_backup_frequency',
        'auto_backup_weekday',
        'auto_backup_time',
        'auto_backup_last_run_at',
        'status',
        'status_message',
        'health_failure_count',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'database_config' => 'encrypted:array',
            'storage_config' => 'encrypted:array',
            'last_verified_at' => 'datetime',
            'auto_backup_enabled' => 'boolean',
            'auto_backup_last_run_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function usesExternalDatabase(): bool
    {
        return $this->database_mode === self::MODE_EXTERNAL;
    }

    public function usesExternalStorage(): bool
    {
        return $this->storage_mode === self::MODE_EXTERNAL;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function hasPendingDatabaseMigration(): bool
    {
        return in_array($this->database_migration_status, [self::MIGRATION_QUEUED, self::MIGRATION_RUNNING], true);
    }

    public function hasPendingStorageMigration(): bool
    {
        return in_array($this->storage_migration_status, [self::MIGRATION_QUEUED, self::MIGRATION_RUNNING], true);
    }

    public function hasPendingBackupExport(): bool
    {
        return in_array($this->backup_export_status, [self::MIGRATION_QUEUED, self::MIGRATION_RUNNING], true);
    }

    public function hasDatabaseTargetConfig(): bool
    {
        return is_array($this->database_config) && $this->database_config !== [];
    }

    public function hasStorageTargetConfig(): bool
    {
        return is_array($this->storage_config) && $this->storage_config !== [];
    }
}
