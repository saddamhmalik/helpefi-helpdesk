<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Support\TenantInfrastructureUserMessage;
use App\Domains\Tenancy\Support\TenantStorageDisks;
use App\Domains\Tickets\Models\TicketAttachment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ManagedToExternalStorageMigrationService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private ExternalTenantStorageService $externalStorage,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function migrate(Tenant $tenant, TenantInfrastructure $record): void
    {
        if (! $record->hasStorageTargetConfig()) {
            throw new RuntimeException('External storage configuration is missing.');
        }

        $this->externalStorage->registerDisk($record);

        $managedDisk = Storage::disk(TenantStorageDisks::MANAGED);
        $externalDisk = Storage::disk(TenantStorageDisks::EXTERNAL);
        $deleteLocal = (bool) config('tenant_infrastructure.delete_local_files_after_storage_migration', true);

        try {
            $tenant->run(function () use ($managedDisk, $externalDisk, $deleteLocal) {
                TicketAttachment::query()
                    ->whereNotNull('path')
                    ->orderBy('id')
                    ->chunkById(100, function ($attachments) use ($managedDisk, $externalDisk, $deleteLocal) {
                        foreach ($attachments as $attachment) {
                            $this->copyObject(
                                $managedDisk,
                                $externalDisk,
                                (string) $attachment->path,
                                $deleteLocal,
                                function () use ($attachment) {
                                    $attachment->storage_disk = TenantStorageDisks::EXTERNAL;
                                    $attachment->save();
                                },
                            );
                        }
                    });

                User::query()
                    ->whereNotNull('avatar_path')
                    ->orderBy('id')
                    ->chunkById(100, function ($users) use ($managedDisk, $externalDisk, $deleteLocal) {
                        foreach ($users as $user) {
                            $this->copyObject(
                                $managedDisk,
                                $externalDisk,
                                (string) $user->avatar_path,
                                $deleteLocal,
                                function () use ($user) {
                                    $user->avatar_disk = TenantStorageDisks::EXTERNAL;
                                    $user->save();
                                },
                                (string) ($user->avatar_disk ?? TenantStorageDisks::MANAGED),
                            );
                        }
                    });
            });

            $record->storage_mode = TenantInfrastructure::MODE_EXTERNAL;
            $record->storage_migration_status = TenantInfrastructure::MIGRATION_COMPLETED;
            $record->status = TenantInfrastructure::STATUS_PENDING;
            $record->status_message = 'Storage migration completed. Run verify to confirm external storage.';
            $this->infrastructure->save($record);

            $this->audit->record(
                'platform.tenant.infrastructure_storage_migrated',
                $tenant,
                [
                    'prefix' => $this->externalStorage->resolvePrefix($record),
                ],
                tenantId: $tenant->id,
            );
        } catch (Throwable $exception) {
            $record->storage_migration_status = TenantInfrastructure::MIGRATION_FAILED;
            $record->status = TenantInfrastructure::STATUS_FAILED;
            $record->status_message = TenantInfrastructureUserMessage::migrationFailed(
                'storage',
                $exception->getMessage(),
            );
            $this->infrastructure->save($record);

            $this->audit->record(
                'platform.tenant.infrastructure_migration_failed',
                $tenant,
                [
                    'type' => 'storage',
                    'message' => $exception->getMessage(),
                ],
                tenantId: $tenant->id,
            );

            throw $exception;
        } finally {
            $this->externalStorage->unregisterDisk();
        }
    }

    private function copyObject(
        $managedDisk,
        $externalDisk,
        string $path,
        bool $deleteLocal,
        callable $afterCopy,
        ?string $sourceDisk = null,
    ): void {
        if ($sourceDisk === TenantStorageDisks::EXTERNAL) {
            return;
        }

        if ($path === '') {
            return;
        }

        if (! $managedDisk->exists($path)) {
            return;
        }

        $stream = $managedDisk->readStream($path);

        if ($stream === false) {
            throw new RuntimeException('Failed to read managed file: '.$path);
        }

        if (! $externalDisk->writeStream($path, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('Failed to write external file: '.$path);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        $afterCopy();

        if ($deleteLocal) {
            $managedDisk->delete($path);
        }
    }
}
