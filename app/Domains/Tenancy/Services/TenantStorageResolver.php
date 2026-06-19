<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Repositories\TenantInfrastructureRepository;
use App\Domains\Tenancy\Support\TenantStorageDisks;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class TenantStorageResolver
{
    public function __construct(private TenantInfrastructureRepository $infrastructure)
    {
    }

    public function usesExternalStorage(): bool
    {
        if (! tenancy()->initialized) {
            return false;
        }

        $tenant = tenant();
        $record = $tenant ? $this->infrastructure->findForTenant($tenant->id) : null;

        return $record !== null && $record->usesExternalStorage();
    }

    public function diskName(): string
    {
        return $this->usesExternalStorage() ? TenantStorageDisks::EXTERNAL : TenantStorageDisks::MANAGED;
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskFor(?string $storageDisk): Filesystem
    {
        return Storage::disk($this->resolveDiskName($storageDisk));
    }

    public function url(string $path, ?string $storageDisk = null): string
    {
        $diskName = $this->resolveDiskName($storageDisk);
        $disk = Storage::disk($diskName);

        if ($diskName === TenantStorageDisks::EXTERNAL) {
            return $disk->temporaryUrl($path, $this->signedUrlExpiry());
        }

        return $disk->url($path);
    }

    public function temporaryUrl(string $path, ?DateTimeInterface $expiresAt = null, ?string $storageDisk = null): string
    {
        return Storage::disk($this->resolveDiskName($storageDisk))->temporaryUrl(
            $path,
            $expiresAt ?? $this->signedUrlExpiry(),
        );
    }

    public function get(string $path, ?string $storageDisk = null): ?string
    {
        $disk = $this->diskFor($storageDisk);

        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->get($path);
    }

    public function delete(string $path, ?string $storageDisk = null): bool
    {
        return $this->diskFor($storageDisk)->delete($path);
    }

    private function resolveDiskName(?string $storageDisk): string
    {
        if ($storageDisk === TenantStorageDisks::EXTERNAL) {
            return TenantStorageDisks::EXTERNAL;
        }

        if ($storageDisk === TenantStorageDisks::MANAGED || $storageDisk === 'public') {
            return TenantStorageDisks::MANAGED;
        }

        return TenantStorageDisks::MANAGED;
    }

    private function signedUrlExpiry(): DateTimeInterface
    {
        $minutes = max(5, (int) config('tenant_infrastructure.signed_url_minutes', 30));

        return now()->addMinutes($minutes);
    }
}
