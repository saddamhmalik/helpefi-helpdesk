<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Models\TenantInfrastructureBackup;
use App\Domains\Tenancy\Repositories\TenantInfrastructureBackupRepository;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Support\TenantStorageDisks;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TenantInfrastructureBackupCatalogService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private TenantInfrastructureBackupRepository $backups,
        private ExternalTenantStorageService $externalStorage,
    ) {
    }

    public function listForTenant(Tenant $tenant): array
    {
        $record = $this->requireExternalStorage($tenant);

        return $this->withExternalDisk($record, function () use ($tenant) {
            $paths = collect(Storage::disk(TenantStorageDisks::EXTERNAL)->files('backups'))
                ->filter(fn (string $path) => str_ends_with(strtolower($path), '.sql'))
                ->values();

            $existingKeys = [];

            foreach ($paths as $path) {
                $size = (int) Storage::disk(TenantStorageDisks::EXTERNAL)->size($path);
                $storedAt = Carbon::createFromTimestamp(
                    Storage::disk(TenantStorageDisks::EXTERNAL)->lastModified($path),
                );

                $this->backups->upsertFromObject($tenant->id, $path, $size, $storedAt);
                $existingKeys[] = $path;
            }

            $this->backups->deleteMissingKeys($tenant->id, $existingKeys);

            return $this->backups->forTenant($tenant->id)
                ->map(fn (TenantInfrastructureBackup $backup) => $this->present($backup))
                ->values()
                ->all();
        });
    }

    public function updateLabel(Tenant $tenant, string $backupId, string $label): array
    {
        $backup = $this->backups->findForTenant($tenant->id, $backupId);

        if ($backup === null) {
            throw ValidationException::withMessages([
                'backup' => 'Backup not found.',
            ]);
        }

        $backup->label = trim($label) !== '' ? trim($label) : basename($backup->object_key);
        $this->backups->save($backup);

        return $this->present($backup);
    }

    public function delete(Tenant $tenant, string $backupId): void
    {
        $record = $this->requireExternalStorage($tenant);
        $backup = $this->backups->findForTenant($tenant->id, $backupId);

        if ($backup === null) {
            throw ValidationException::withMessages([
                'backup' => 'Backup not found.',
            ]);
        }

        $this->withExternalDisk($record, function () use ($backup) {
            if (Storage::disk(TenantStorageDisks::EXTERNAL)->exists($backup->object_key)) {
                Storage::disk(TenantStorageDisks::EXTERNAL)->delete($backup->object_key);
            }
        });

        $this->backups->delete($backup);
    }

    public function registerUploadedObject(
        Tenant $tenant,
        string $objectKey,
        int $size,
        ?string $label = null,
    ): TenantInfrastructureBackup {
        return $this->backups->upsertFromObject(
            $tenant->id,
            $objectKey,
            $size,
            now(),
            $label,
        );
    }

    public function backupObjectKey(): string
    {
        return 'backups/database-'.now()->format('Y-m-d-His').'.sql';
    }

    private function present(TenantInfrastructureBackup $backup): array
    {
        return [
            'id' => $backup->id,
            'object_key' => $backup->object_key,
            'label' => $backup->label ?? basename($backup->object_key),
            'size' => (int) $backup->size,
            'stored_at' => $backup->stored_at?->toIso8601String(),
        ];
    }

    private function requireExternalStorage(Tenant $tenant): TenantInfrastructure
    {
        $record = $this->infrastructure->findForTenant($tenant->id);

        if ($record === null || ! $record->usesExternalStorage()) {
            throw ValidationException::withMessages([
                'storage' => 'External storage must be configured to manage backups.',
            ]);
        }

        return $record;
    }

    private function withExternalDisk(TenantInfrastructure $record, callable $callback): mixed
    {
        $this->externalStorage->registerDisk($record);

        try {
            return $callback();
        } finally {
            $this->externalStorage->unregisterDisk();
        }
    }
}
