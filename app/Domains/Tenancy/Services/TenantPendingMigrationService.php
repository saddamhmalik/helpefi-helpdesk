<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migrator;

class TenantPendingMigrationService
{
    public function __construct(
        private TenantInfrastructureRepository $infrastructure,
        private TenantInfrastructureAlertService $alerts,
        private PlatformAuditRecorder $audit,
        private Migrator $migrator,
    ) {
    }

    public function checkAll(): int
    {
        $alerted = 0;

        foreach (Tenant::query()->cursor() as $tenant) {
            $pending = $this->pendingCount($tenant);

            if ($pending < 1) {
                continue;
            }

            $message = "{$pending} pending tenant migration(s) for workspace {$tenant->slug}.";
            $record = $this->infrastructure->findForTenant($tenant->id);

            if ($record !== null && $record->usesExternalDatabase()) {
                $this->alerts->notifyFailure($record->fresh(['tenant']), $message, 'pending_migrations');
            }

            $this->audit->record(
                'platform.tenant.infrastructure_migration_pending',
                $tenant,
                [
                    'pending_count' => $pending,
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
}
