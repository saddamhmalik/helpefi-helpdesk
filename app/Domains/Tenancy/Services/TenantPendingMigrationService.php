<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Services\TenantReleaseUpgradeService;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migrator;

class TenantPendingMigrationService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private TenantInfrastructureAlertService $alerts,
        private PlatformAuditRecorder $audit,
        private Migrator $migrator,
        private TenantReleaseUpgradeService $releases,
    ) {
    }

    public function checkAll(): int
    {
        $alerted = 0;

        foreach (Tenant::query()->cursor() as $tenant) {
            $schemaPending = $this->pendingSchemaCount($tenant);
            $releasePending = $this->pendingReleaseStepCount($tenant);

            if ($schemaPending < 1 && $releasePending < 1) {
                continue;
            }

            $parts = [];

            if ($schemaPending > 0) {
                $parts[] = "{$schemaPending} schema migration(s)";
            }

            if ($releasePending > 0) {
                $parts[] = "{$releasePending} release step(s)";
            }

            $message = implode(' and ', $parts)." pending for workspace {$tenant->slug}.";
            $record = $this->infrastructure->findForTenant($tenant->id);

            if ($record !== null && $record->usesExternalDatabase()) {
                $this->alerts->notifyFailure($record->fresh(['tenant']), $message, 'pending_migrations');
            }

            $this->audit->record(
                'platform.tenant.infrastructure_migration_pending',
                $tenant,
                [
                    'pending_schema_count' => $schemaPending,
                    'pending_release_count' => $releasePending,
                    'source' => 'scheduled_check',
                ],
                tenantId: $tenant->id,
            );

            $alerted++;
        }

        return $alerted;
    }

    public function pendingCount(Tenant $tenant): int
    {
        return $this->pendingSchemaCount($tenant) + $this->pendingReleaseStepCount($tenant);
    }

    public function pendingSchemaCount(Tenant $tenant): int
    {
        $paths = config('tenancy.migration_parameters.--path', [database_path('migrations/tenant')]);
        $paths = is_array($paths) ? $paths : [$paths];
        $pending = 0;

        $tenant->run(function () use ($paths, &$pending) {
            $files = $this->migrator->getMigrationFiles($paths);
            $ran = $this->migrator->getRepository()->getRan();
            $pending = count(array_diff(array_keys($files), $ran));
        });

        return $pending;
    }

    public function pendingReleaseStepCount(Tenant $tenant): int
    {
        return $this->releases->pendingStepCount($tenant);
    }
}
