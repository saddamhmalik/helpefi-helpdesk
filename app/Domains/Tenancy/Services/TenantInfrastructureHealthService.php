<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;

class TenantInfrastructureHealthService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private ExternalTenantDatabaseService $database,
        private ExternalTenantStorageHealthChecker $storage,
        private TenantInfrastructureMetricsService $metrics,
        private TenantInfrastructureAlertService $alerts,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function checkAll(): int
    {
        $checked = 0;

        foreach ($this->infrastructure->allExternal() as $record) {
            $this->check($record);
            $checked++;
        }

        return $checked;
    }

    public function check(TenantInfrastructure $record): void
    {
        if (! $record->usesExternalDatabase() && ! $record->usesExternalStorage()) {
            return;
        }

        $errors = [];

        if ($record->usesExternalDatabase()) {
            $databaseError = $this->database->testConnection($record->database_config ?? []);

            if ($databaseError !== null) {
                $errors[] = $databaseError;
            }
        }

        if ($record->usesExternalStorage()) {
            $storageError = $this->storage->ping($record);

            if ($storageError !== null) {
                $errors[] = $storageError;
            }
        }

        if ($errors !== []) {
            $this->handleFailure($record, implode(' ', $errors));

            return;
        }

        $this->handleSuccess($record);
    }

    private function handleFailure(TenantInfrastructure $record, string $message): void
    {
        $previousStatus = $record->status;
        $threshold = max(1, (int) config('tenant_infrastructure.health_failure_threshold', 3));
        $record->health_failure_count = min($threshold, (int) $record->health_failure_count + 1);

        if ($record->health_failure_count < $threshold) {
            $record->status_message = "Health check warning ({$record->health_failure_count}/{$threshold}): {$message}";
            $this->infrastructure->save($record);

            return;
        }

        $this->infrastructure->markStatus($record, TenantInfrastructure::STATUS_FAILED, $message);
        $record->health_failure_count = $threshold;
        $this->infrastructure->save($record);

        if ($previousStatus === TenantInfrastructure::STATUS_VERIFIED) {
            $this->metrics->incrementHealthFailures();

            $this->audit->record(
                'platform.tenant.infrastructure_failed',
                $record->tenant,
                [
                    'tenant_id' => $record->tenant_id,
                    'message' => $message,
                    'source' => 'health_check',
                ],
                tenantId: $record->tenant_id,
            );

            $this->alerts->notifyFailure($record->fresh(['tenant']), $message, 'health_check');
        }
    }

    private function handleSuccess(TenantInfrastructure $record): void
    {
        $hadWarnings = (int) $record->health_failure_count > 0;
        $previousStatus = $record->status;
        $record->health_failure_count = 0;

        if ($record->status === TenantInfrastructure::STATUS_PENDING) {
            $record->status_message = null;
            $this->infrastructure->save($record);

            return;
        }

        if ($previousStatus === TenantInfrastructure::STATUS_FAILED && $record->last_verified_at !== null) {
            $this->infrastructure->markStatus($record, TenantInfrastructure::STATUS_VERIFIED, null, true);

            if ($hadWarnings || $previousStatus === TenantInfrastructure::STATUS_FAILED) {
                $this->audit->record(
                    'platform.tenant.infrastructure_verified',
                    $record->tenant,
                    [
                        'tenant_id' => $record->tenant_id,
                        'source' => 'health_check',
                    ],
                    tenantId: $record->tenant_id,
                );
            }

            return;
        }

        if ($record->status === TenantInfrastructure::STATUS_VERIFIED) {
            $record->status_message = null;
            $this->infrastructure->save($record);
        }
    }
}
