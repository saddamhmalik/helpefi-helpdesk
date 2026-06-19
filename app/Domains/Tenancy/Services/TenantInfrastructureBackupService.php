<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TenantInfrastructureBackupService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private TenantInfrastructureAutoBackupScheduleService $schedule,
        private TenantInfrastructureBackupCatalogService $catalog,
        private ExternalTenantBackupService $exports,
        private TenantByoEligibilityService $eligibility,
    ) {
    }

    public function snapshotForTenant(Tenant $tenant): ?array
    {
        $record = $this->infrastructure->findForTenant($tenant->id);

        if ($record === null || ! $record->usesExternalStorage()) {
            return null;
        }

        $backups = [];

        try {
            $backups = $this->catalog->listForTenant($tenant);
        } catch (\Throwable) {
            $backups = [];
        }

        return [
            'schedule' => $this->schedule->snapshot($record),
            'schedule_options' => $this->schedule->options(),
            'backups' => $backups,
        ];
    }

    public function updateSchedule(Tenant $tenant, array $data): array
    {
        $this->eligibility->assertCanConfigureStorage($tenant, includeLegacyAllowlist: false);

        $record = $this->requireExternalStorageRecord($tenant);

        if (! $record->usesExternalDatabase()) {
            throw ValidationException::withMessages([
                'auto_backup' => 'Automatic database backups require an external database workspace.',
            ]);
        }

        $record = $this->schedule->apply($record, $data);
        $this->infrastructure->save($record);

        return $this->schedule->snapshot($record->fresh());
    }

    public function updateBackupLabel(Tenant $tenant, string $backupId, string $label): array
    {
        $this->eligibility->assertCanConfigureStorage($tenant, includeLegacyAllowlist: false);

        return $this->catalog->updateLabel($tenant, $backupId, $label);
    }

    public function deleteBackup(Tenant $tenant, string $backupId): void
    {
        $this->eligibility->assertCanConfigureStorage($tenant, includeLegacyAllowlist: false);

        $this->catalog->delete($tenant, $backupId);
    }

    public function processDueSchedules(): int
    {
        $queued = 0;

        foreach ($this->dueRecords() as $record) {
            $tenant = $record->tenant;

            if ($tenant === null) {
                continue;
            }

            try {
                $this->exports->queueExportDatabaseToCustomerBucket($tenant, scheduled: true);
                $queued++;
            } catch (\Throwable) {
            }
        }

        return $queued;
    }

    private function dueRecords(): Collection
    {
        return TenantInfrastructure::query()
            ->with('tenant')
            ->where('auto_backup_enabled', true)
            ->where('storage_mode', TenantInfrastructure::MODE_EXTERNAL)
            ->where('database_mode', TenantInfrastructure::MODE_EXTERNAL)
            ->where('status', TenantInfrastructure::STATUS_VERIFIED)
            ->get()
            ->filter(fn (TenantInfrastructure $record) => $this->schedule->isDue($record));
    }

    private function requireExternalStorageRecord(Tenant $tenant): TenantInfrastructure
    {
        $record = $this->infrastructure->findForTenant($tenant->id);

        if ($record === null || ! $record->usesExternalStorage()) {
            throw ValidationException::withMessages([
                'storage' => 'External storage must be configured.',
            ]);
        }

        return $record;
    }
}
