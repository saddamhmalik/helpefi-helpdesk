<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExternalTenantStorageHealthChecker
{
    private const HEALTH_DISK = 'tenant_infrastructure_health';

    public function __construct(private ExternalTenantStorageService $storage)
    {
    }

    public function ping(TenantInfrastructure $infrastructure): ?string
    {
        try {
            Config::set('filesystems.disks.'.self::HEALTH_DISK, $this->storage->buildDiskConfig($infrastructure));
            Storage::disk(self::HEALTH_DISK)->exists($this->storage->verifyObjectKey());

            return null;
        } catch (Throwable $exception) {
            return 'Storage health check failed: '.$exception->getMessage();
        } finally {
            try {
                Storage::forgetDisk(self::HEALTH_DISK);
            } catch (Throwable) {
            }

            Config::offsetUnset('filesystems.disks.'.self::HEALTH_DISK);
        }
    }
}
