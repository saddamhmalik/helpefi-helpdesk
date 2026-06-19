<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExternalTenantStorageTester
{
    private const TEST_DISK = 'tenant_infrastructure_storage_test';

    public function __construct(private ExternalTenantStorageService $storage)
    {
    }

    public function test(TenantInfrastructure $infrastructure): ?string
    {
        $payload = 'helpefi-storage-verify-'.now()->timestamp;
        $objectKey = $this->storage->verifyObjectKey();

        try {
            Config::set('filesystems.disks.'.self::TEST_DISK, $this->storage->buildDiskConfig($infrastructure));
            $disk = Storage::disk(self::TEST_DISK);
            $disk->put($objectKey, $payload);

            if (! $disk->exists($objectKey)) {
                return 'Storage verification failed: unable to write the test object.';
            }

            $contents = $disk->get($objectKey);

            if ($contents !== $payload) {
                return 'Storage verification failed: test object content mismatch.';
            }

            $disk->delete($objectKey);

            if ($disk->exists($objectKey)) {
                return 'Storage verification failed: unable to delete the test object.';
            }

            return null;
        } catch (Throwable $exception) {
            return 'Storage connection failed: '.$exception->getMessage();
        } finally {
            try {
                Storage::forgetDisk(self::TEST_DISK);
            } catch (\Throwable) {
            }

            Config::offsetUnset('filesystems.disks.'.self::TEST_DISK);
        }
    }
}
