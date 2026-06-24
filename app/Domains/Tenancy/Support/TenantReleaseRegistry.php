<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Tenancy\Contracts\TenantReleaseStep;
use InvalidArgumentException;

class TenantReleaseRegistry
{
    public function targetRelease(): string
    {
        return \App\Support\AppVersion::current();
    }

    public function orderedReleases(): array
    {
        $releases = array_keys(config('tenant-releases.releases', []));
        usort($releases, 'version_compare');

        return $releases;
    }

    public function releaseDescription(string $release): string
    {
        return (string) ($this->releaseConfig($release)['description'] ?? '');
    }

    public function stepsForRelease(string $release): array
    {
        $config = $this->releaseConfig($release);

        if ($config === null) {
            throw new InvalidArgumentException("Unknown tenant release [{$release}].");
        }

        $steps = [];

        foreach ($config['steps'] ?? [] as $class) {
            $steps[] = $this->makeStep($class, $release);
        }

        return $steps;
    }

    public function allStepsUpTo(string $targetRelease): array
    {
        $steps = [];

        foreach ($this->orderedReleases() as $release) {
            if (version_compare($release, $targetRelease, '>')) {
                break;
            }

            foreach ($this->stepsForRelease($release) as $step) {
                $steps[] = $step;
            }
        }

        return $steps;
    }

    private function makeStep(string $class, string $release): TenantReleaseStep
    {
        $step = app($class, ['release' => $release]);

        if (! $step instanceof TenantReleaseStep) {
            throw new InvalidArgumentException("Release step [{$class}] must implement TenantReleaseStep.");
        }

        return $step;
    }

    private function releaseConfig(string $release): ?array
    {
        $releases = config('tenant-releases.releases', []);

        return $releases[$release] ?? null;
    }
}
