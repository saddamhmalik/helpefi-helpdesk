<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Jobs\RunAssetDiscoveryScanJob;
use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetDiscoveryDevice;
use App\Domains\Assets\Models\AssetDiscoveryScan;
use App\Domains\Assets\Repositories\AssetDiscoveryRepository;
use App\Domains\Assets\Repositories\AssetRepository;
use App\Domains\Assets\Repositories\AssetTypeRepository;
use App\Domains\Assets\Support\DeviceNameResolver;
use App\Domains\Assets\Support\NetworkDiscoveryScanner;
use App\Domains\Billing\Services\BillingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class AssetDiscoveryService
{
    public function __construct(
        private AssetDiscoveryRepository $discovery,
        private AssetRepository $assets,
        private AssetTypeRepository $types,
        private BillingService $billing,
        private DeviceNameResolver $names,
        private NetworkDiscoveryScanner $scanner,
    ) {
    }

    public function listScans(int $perPage = 10): LengthAwarePaginator
    {
        $this->discovery->expireStaleRunningScans();

        return $this->discovery->paginateScans($perPage);
    }

    public function showScan(int $id): AssetDiscoveryScan
    {
        $this->discovery->expireStaleRunningScans();

        return $this->discovery->findScan($id);
    }

    public function startScan(string $subnet, int $userId): AssetDiscoveryScan
    {
        $this->billing->assertFeature('assets');

        $this->scanner->validateSubnet($subnet);

        $scan = $this->discovery->createScan($subnet, $userId);
        RunAssetDiscoveryScanJob::dispatch($scan->id);

        return $scan;
    }

    public function importDevices(int $scanId, array $deviceIds, int $assetTypeId, array $deviceNames = []): array
    {
        $this->billing->assertFeature('assets');

        $scan = $this->discovery->findScan($scanId);
        $devices = $this->discovery->devicesForImport($scan, $deviceIds);

        if ($devices->isEmpty()) {
            throw new InvalidArgumentException('Select at least one device to import.');
        }

        $type = $this->types->all()->firstWhere('id', $assetTypeId);

        if (! $type) {
            throw new InvalidArgumentException('Select a valid asset type.');
        }

        $imported = 0;
        $updated = 0;

        foreach ($devices as $device) {
            if ($device->status === AssetDiscoveryDevice::STATUS_MATCHED && $device->matched_asset_id) {
                $asset = $this->assets->find($device->matched_asset_id);
                $this->assets->update($asset, [
                    'hostname' => $device->hostname ?? $asset->hostname,
                    'last_seen_at' => now(),
                    'discovery_source' => 'network_scan',
                ]);
                $device->update(['status' => AssetDiscoveryDevice::STATUS_IMPORTED]);
                $updated++;

                continue;
            }

            $customName = trim((string) ($deviceNames[$device->id] ?? $deviceNames[(string) $device->id] ?? ''));
            $name = $customName !== ''
                ? $customName
                : $this->names->displayName($device->ip_address, $device->hostname, $device->vendor);
            $asset = $this->assets->create([
                'asset_type_id' => $assetTypeId,
                'name' => $name,
                'status' => Asset::STATUS_IN_USE,
                'ip_address' => $device->ip_address,
                'mac_address' => $device->mac_address,
                'hostname' => $device->hostname ?: $name,
                'manufacturer' => $device->vendor,
                'last_seen_at' => now(),
                'discovery_source' => 'network_scan',
            ]);

            $device->update([
                'status' => AssetDiscoveryDevice::STATUS_IMPORTED,
                'imported_asset_id' => $asset->id,
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
        ];
    }
}
