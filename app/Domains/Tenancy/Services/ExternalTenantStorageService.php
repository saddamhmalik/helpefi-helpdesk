<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\TenantInfrastructure;
use App\Domains\Tenancy\Support\TenantStorageDisks;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ExternalTenantStorageService
{
    private const R2_REGIONS = ['auto', 'wnam', 'enam', 'weur', 'eeur', 'apac', 'oc'];

    private const R2_REGION_ALIASES = [
        'asia-pacific' => 'apac',
        'asia pacific' => 'apac',
        'western europe' => 'weur',
        'eastern europe' => 'eeur',
        'western north america' => 'wnam',
        'eastern north america' => 'enam',
        'north america' => 'auto',
        'europe' => 'auto',
        'oceania' => 'oc',
        'australia' => 'oc',
    ];

    public function registerDisk(TenantInfrastructure $infrastructure): void
    {
        Config::set('filesystems.disks.'.TenantStorageDisks::EXTERNAL, $this->buildDiskConfig($infrastructure));
    }

    public function unregisterDisk(): void
    {
        try {
            Storage::forgetDisk(TenantStorageDisks::EXTERNAL);
        } catch (\Throwable) {
        }

        Config::offsetUnset('filesystems.disks.'.TenantStorageDisks::EXTERNAL);
    }

    public function buildDiskConfig(TenantInfrastructure $infrastructure): array
    {
        $config = $infrastructure->storage_config ?? [];
        $driver = $config['driver'] ?? 's3';
        $region = $config['region'] ?? null;

        if ($driver === 'r2') {
            $region = $this->resolveR2Region($region);
        } elseif (! filled($region)) {
            $region = 'us-east-1';
        }

        $disk = [
            'driver' => 's3',
            'key' => $config['access_key_id'] ?? '',
            'secret' => $config['secret_access_key'] ?? '',
            'region' => $region,
            'bucket' => $config['bucket'] ?? '',
            'root' => $this->resolvePrefix($infrastructure),
            'throw' => true,
            'report' => false,
        ];

        if ($driver === 'r2') {
            $disk['endpoint'] = $config['endpoint'] ?? null;
            $disk['use_path_style_endpoint'] = true;
        }

        if (filled($config['cdn_url'] ?? null)) {
            $disk['url'] = rtrim((string) $config['cdn_url'], '/');
        }

        return $disk;
    }

    public function resolvePrefix(TenantInfrastructure $infrastructure): string
    {
        $config = $infrastructure->storage_config ?? [];
        $prefix = trim((string) ($config['prefix'] ?? ''), '/');

        if ($prefix === '') {
            $prefix = trim(config('tenant_infrastructure.storage_key_prefix', 'helpefi'), '/')
                .'/'
                .$infrastructure->tenant_id;
        }

        return $prefix;
    }

    public function verifyObjectKey(): string
    {
        return ltrim((string) config('tenant_infrastructure.verify_test_object', '.helpefi-verify'), '/');
    }

    public function resolveR2Region(?string $region): string
    {
        $normalized = strtolower(trim((string) $region));

        if ($normalized === '') {
            return 'auto';
        }

        if (isset(self::R2_REGION_ALIASES[$normalized])) {
            return self::R2_REGION_ALIASES[$normalized];
        }

        if (in_array($normalized, self::R2_REGIONS, true)) {
            return $normalized;
        }

        throw new \InvalidArgumentException(
            'Invalid Cloudflare R2 region. Use auto, apac, wnam, enam, weur, eeur, or oc. '
            .'Dashboard labels such as "Asia-Pacific" map to apac; leave blank for auto.'
        );
    }
}
