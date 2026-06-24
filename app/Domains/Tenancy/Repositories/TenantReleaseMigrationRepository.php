<?php

namespace App\Domains\Tenancy\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantReleaseMigrationRepository
{
    public function tableExists(): bool
    {
        return Schema::hasTable('tenant_release_migrations');
    }

    public function hasRun(string $release, string $step): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        return DB::table('tenant_release_migrations')
            ->where('release', $release)
            ->where('step', $step)
            ->exists();
    }

    public function record(string $release, string $step, int $batch): void
    {
        DB::table('tenant_release_migrations')->insert([
            'release' => $release,
            'step' => $step,
            'batch' => $batch,
            'ran_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function ranStepKeys(): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        return DB::table('tenant_release_migrations')
            ->orderBy('id')
            ->get(['release', 'step'])
            ->map(fn ($row) => "{$row->release}:{$row->step}");
    }

    public function completedReleases(array $orderedReleases, array $stepsByRelease): array
    {
        $ran = $this->ranStepKeys()->flip();
        $completed = [];

        foreach ($orderedReleases as $release) {
            $steps = $stepsByRelease[$release] ?? [];

            if ($steps === []) {
                $completed[] = $release;

                continue;
            }

            $allRan = collect($steps)->every(
                fn (string $stepKey) => $ran->has("{$release}:{$stepKey}"),
            );

            if ($allRan) {
                $completed[] = $release;
            }
        }

        return $completed;
    }

    public function nextBatch(): int
    {
        if (! $this->tableExists()) {
            return 1;
        }

        return ((int) DB::table('tenant_release_migrations')->max('batch')) + 1;
    }
}
