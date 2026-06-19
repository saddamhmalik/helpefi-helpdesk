<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Jobs\CreatePlatformBackupJob;
use App\Domains\Platform\Models\PlatformBackup;
use App\Domains\Platform\Repositories\PlatformBackupRepository;
use App\Domains\Platform\Repositories\PlatformTenantRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Services\TenantInfrastructureService;
use App\Models\PlatformUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformBackupService
{
    public function __construct(
        private PlatformBackupRepository $backups,
        private PlatformTenantRepository $tenants,
        private PlatformAuditRecorder $audit,
        private TenantInfrastructureService $infrastructure,
    ) {
    }

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return $this->backups->paginate($perPage);
    }

    public function workspaceOptions(): Collection
    {
        return $this->tenants->allForSelect();
    }

    public function queueCentral(PlatformUser $actor): PlatformBackup
    {
        return $this->queue(PlatformBackup::SCOPE_CENTRAL, null, $actor);
    }

    public function queueTenant(string $tenantId, PlatformUser $actor): PlatformBackup
    {
        $tenant = $this->tenants->find($tenantId);

        if ($this->infrastructure->usesExternalDatabase($tenant)) {
            throw ValidationException::withMessages([
                'tenant' => 'Central backups are not available for workspaces with an external database.',
            ]);
        }

        return $this->queue(PlatformBackup::SCOPE_TENANT, $tenantId, $actor);
    }

    public function queueAllTenants(PlatformUser $actor): array
    {
        $queued = [];
        $skipped = 0;

        foreach ($this->tenants->allForSelect() as $tenant) {
            if ($this->infrastructure->usesExternalDatabase($tenant)) {
                $skipped++;

                continue;
            }

            $queued[] = $this->queue(PlatformBackup::SCOPE_TENANT, $tenant->id, $actor);
        }

        $this->audit->record('platform.backup.created', properties: [
            'scope' => PlatformBackup::SCOPE_ALL_TENANTS,
            'count' => count($queued),
            'skipped_external_database' => $skipped,
        ]);

        return $queued;
    }

    public function download(int $id): StreamedResponse
    {
        $backup = $this->backups->find($id);

        if ($backup->status !== PlatformBackup::STATUS_COMPLETED || ! $backup->path) {
            abort(404, 'Backup file is not available.');
        }

        $disk = Storage::disk($backup->storage_disk);

        if (! $disk->exists($backup->path)) {
            abort(404, 'Backup file is missing from storage.');
        }

        return $disk->download($backup->path, basename($backup->path));
    }

    public function delete(int $id): void
    {
        $backup = $this->backups->find($id);

        if ($backup->path) {
            Storage::disk($backup->storage_disk)->delete($backup->path);
        }

        $this->audit->record('platform.backup.deleted', $backup, [
            'scope' => $backup->scope,
            'tenant_id' => $backup->tenant_id,
        ], tenantId: $backup->tenant_id);

        $this->backups->delete($backup);
    }

    public function purgeExpired(): int
    {
        $retentionDays = (int) config('backup.retention_days', 30);
        $purged = 0;

        foreach ($this->backups->expired($retentionDays) as $backup) {
            $this->delete($backup->id);
            $purged++;
        }

        return $purged;
    }

    private function queue(string $scope, ?string $tenantId, PlatformUser $actor): PlatformBackup
    {
        $backup = $this->backups->create([
            'scope' => $scope,
            'tenant_id' => $tenantId,
            'status' => PlatformBackup::STATUS_PENDING,
            'storage_disk' => config('backup.disk', 'local'),
            'created_by' => $actor->id,
        ]);

        CreatePlatformBackupJob::dispatch($backup->id);

        $this->audit->record('platform.backup.created', $backup, [
            'scope' => $scope,
            'tenant_id' => $tenantId,
        ], tenantId: $tenantId);

        return $backup;
    }
}
