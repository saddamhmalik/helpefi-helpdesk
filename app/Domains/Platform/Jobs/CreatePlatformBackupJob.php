<?php

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Concerns\RunsOnCentralQueue;
use App\Domains\Platform\Models\PlatformBackup;
use App\Domains\Platform\Repositories\PlatformBackupRepository;
use App\Domains\Platform\Support\DatabaseBackupExporter;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreatePlatformBackupJob implements ShouldQueue
{
    use Queueable;
    use RunsOnCentralQueue;

    public int $tries = 1;

    public function __construct(public int $backupId)
    {
        $this->bindToCentralQueue();
    }

    public function handle(
        PlatformBackupRepository $backups,
        DatabaseBackupExporter $exporter,
        PlatformAuditRecorder $audit,
    ): void {
        $this->ensureCentralContext();

        $backup = $backups->find($this->backupId);

        $backups->update($backup, ['status' => PlatformBackup::STATUS_RUNNING]);

        try {
            $path = match ($backup->scope) {
                PlatformBackup::SCOPE_CENTRAL => $this->backupCentral($exporter, $backup),
                PlatformBackup::SCOPE_TENANT => $this->backupTenant($exporter, $backup),
                default => throw new \RuntimeException("Unsupported backup scope [{$backup->scope}]."),
            };

            $this->ensureCentralContext();

            $fullPath = Storage::disk($backup->storage_disk)->path($path);

            if (! is_file($fullPath)) {
                throw new \RuntimeException('Backup file was not created.');
            }

            $backups->update($backup->fresh(), [
                'status' => PlatformBackup::STATUS_COMPLETED,
                'path' => $path,
                'size_bytes' => filesize($fullPath) ?: 0,
                'checksum' => is_file($fullPath) ? hash_file('sha256', $fullPath) : null,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $audit->record('platform.backup.completed', $backup->fresh(), [
                'scope' => $backup->scope,
                'tenant_id' => $backup->tenant_id,
                'path' => $path,
            ], tenantId: $backup->tenant_id);
        } catch (Throwable $exception) {
            $backups->update($backup->fresh(), [
                'status' => PlatformBackup::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $audit->record('platform.backup.failed', $backup->fresh(), [
                'scope' => $backup->scope,
                'tenant_id' => $backup->tenant_id,
                'error' => $exception->getMessage(),
            ], tenantId: $backup->tenant_id);

            throw $exception;
        }
    }

    private function backupCentral(DatabaseBackupExporter $exporter, PlatformBackup $backup): string
    {
        $path = $this->buildPath(
            'central',
            'central-'.now()->format('Y-m-d-His').$this->extensionForConnection('central'),
            $backup->storage_disk,
        );

        $exporter->exportConnection('central', Storage::disk($backup->storage_disk)->path($path));

        return $path;
    }

    private function backupTenant(DatabaseBackupExporter $exporter, PlatformBackup $backup): string
    {
        $tenant = Tenant::query()->findOrFail($backup->tenant_id);
        $path = $this->buildPath(
            'tenants/'.$tenant->id,
            $tenant->slug.'-'.now()->format('Y-m-d-His').$this->defaultBackupExtension(),
            $backup->storage_disk,
        );
        $fullPath = Storage::disk($backup->storage_disk)->path($path);

        $tenant->run(function () use ($exporter, $fullPath) {
            $exporter->exportConnection(config('database.default'), $fullPath);
        });

        return $path;
    }

    private function buildPath(string $segment, string $filename, string $disk): string
    {
        $path = trim(config('backup.path'), '/').'/'.$segment.'/'.$filename;
        $directory = dirname(Storage::disk($disk)->path($path));

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $path;
    }

    private function extensionForConnection(string $connectionName): string
    {
        $driver = config("database.connections.{$connectionName}.driver", 'sqlite');

        return $driver === 'sqlite' ? '.sqlite' : '.sql';
    }

    private function defaultBackupExtension(): string
    {
        return $this->extensionForConnection(config('tenancy.database.central_connection', 'central'));
    }
}
