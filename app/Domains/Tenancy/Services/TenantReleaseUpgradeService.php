<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Contracts\TenantReleaseStep;
use App\Domains\Tenancy\Repositories\TenantReleaseMigrationRepository;
use App\Domains\Tenancy\Support\TenantReleaseRegistry;
use App\Models\Tenant;
use App\Support\AppVersion;

class TenantReleaseUpgradeService
{
    public function __construct(
        private TenantReleaseRegistry $registry,
        private TenantReleaseMigrationRepository $migrations,
    ) {
    }

    public function targetRelease(): string
    {
        return $this->registry->targetRelease();
    }

    public function pendingStepCount(Tenant $tenant): int
    {
        return count($this->pendingSteps($tenant));
    }

    public function pendingSteps(Tenant $tenant): array
    {
        $pending = [];

        $tenant->run(function () use (&$pending) {
            $ran = $this->migrations->ranStepKeys()->flip();

            foreach ($this->registry->allStepsUpTo($this->targetRelease()) as $step) {
                if (! $ran->has($step->identifier())) {
                    $pending[] = $step;
                }
            }
        });

        return $pending;
    }

    public function highestCompletedRelease(Tenant $tenant): ?string
    {
        $completed = null;

        $tenant->run(function () use (&$completed) {
            $ran = $this->migrations->ranStepKeys()->flip();
            $completed = $this->resolveHighestCompletedRelease($ran);
        });

        return $completed;
    }

    public function upgradeTenant(
        Tenant $tenant,
        array $options = [],
    ): array {
        $skipKeys = $this->resolveSkipKeys($options);
        $ranSteps = [];
        $batch = null;

        $tenant->run(function () use ($skipKeys, &$ranSteps, &$batch) {
            if (! $this->migrations->tableExists()) {
                throw new \RuntimeException('tenant_release_migrations table is missing. Run tenants:migrate first.');
            }

            $batch = $this->migrations->nextBatch();
            $ran = $this->migrations->ranStepKeys()->flip();

            foreach ($this->registry->allStepsUpTo($this->targetRelease()) as $step) {
                if ($ran->has($step->identifier()) || in_array($step->key(), $skipKeys, true)) {
                    continue;
                }

                $step->run();

                $this->migrations->record($step->release(), $step->key(), $batch);
                $ranSteps[] = $step->identifier();
            }
        });

        $this->syncCentralReleaseMetadata($tenant);

        return [
            'target_release' => $this->targetRelease(),
            'completed_release' => $this->highestCompletedRelease($tenant),
            'ran_steps' => $ranSteps,
            'batch' => $batch,
        ];
    }

    public function status(Tenant $tenant): array
    {
        $pending = $this->pendingSteps($tenant);

        return [
            'target_release' => $this->targetRelease(),
            'current_release' => $tenant->release_version,
            'completed_release' => $this->highestCompletedRelease($tenant),
            'pending_steps' => array_map(fn (TenantReleaseStep $step) => [
                'release' => $step->release(),
                'step' => $step->key(),
                'description' => $step->description(),
            ], $pending),
            'pending_count' => count($pending),
        ];
    }

    private function resolveHighestCompletedRelease(\Illuminate\Support\Collection $ran): ?string
    {
        $highest = null;

        foreach ($this->registry->orderedReleases() as $release) {
            if (version_compare($release, $this->targetRelease(), '>')) {
                break;
            }

            $steps = $this->registry->stepsForRelease($release);

            if ($steps === []) {
                $highest = $release;

                continue;
            }

            $complete = collect($steps)->every(
                fn (TenantReleaseStep $step) => $ran->has($step->identifier()),
            );

            if ($complete) {
                $highest = $release;
            }
        }

        return $highest;
    }

    private function syncCentralReleaseMetadata(Tenant $tenant): void
    {
        $completed = $this->highestCompletedRelease($tenant);

        if ($completed === null) {
            return;
        }

        $tenant->forceFill([
            'release_version' => $completed,
            'release_upgraded_at' => now(),
        ])->save();
    }

    private function resolveSkipKeys(array $options): array
    {
        $skip = [];

        if ($options['skip_handbook'] ?? false) {
            $skip[] = 'sync_platform_handbook';
        }

        if ($options['skip_cache'] ?? false) {
            $skip[] = 'clear_workspace_caches';
        }

        if ($options['skip_permissions'] ?? false) {
            $skip[] = 'sync_agent_permissions';
        }

        return $skip;
    }
}
