<?php

namespace App\Domains\Assets\Repositories;

use App\Domains\Assets\Models\AssetDiscoveryDevice;
use App\Domains\Assets\Models\AssetDiscoveryScan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AssetDiscoveryRepository
{
    public function expireStaleRunningScans(int $minutes = 10): int
    {
        $cutoff = Carbon::now()->subMinutes($minutes);

        return AssetDiscoveryScan::query()
            ->whereIn('status', [
                AssetDiscoveryScan::STATUS_PENDING,
                AssetDiscoveryScan::STATUS_RUNNING,
            ])
            ->where(function ($query) use ($cutoff) {
                $query->where('started_at', '<', $cutoff)
                    ->orWhere(function ($builder) use ($cutoff) {
                        $builder->whereNull('started_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->update([
                'status' => AssetDiscoveryScan::STATUS_FAILED,
                'error_message' => 'Scan timed out before it could finish. Please try again.',
                'completed_at' => now(),
            ]);
    }

    public function paginateScans(int $perPage = 10): LengthAwarePaginator
    {
        return AssetDiscoveryScan::query()
            ->with('startedBy:id,name')
            ->latest()
            ->paginate($perPage);
    }

    public function findScan(int $id): AssetDiscoveryScan
    {
        return AssetDiscoveryScan::query()
            ->with([
                'startedBy:id,name',
                'devices.matchedAsset:id,asset_tag,name',
                'devices.importedAsset:id,asset_tag,name',
            ])
            ->findOrFail($id);
    }

    public function createScan(string $subnet, int $userId): AssetDiscoveryScan
    {
        return AssetDiscoveryScan::query()->create([
            'subnet' => $subnet,
            'status' => AssetDiscoveryScan::STATUS_PENDING,
            'started_by' => $userId,
        ]);
    }

    public function markRunning(AssetDiscoveryScan $scan): AssetDiscoveryScan
    {
        $scan->update([
            'status' => AssetDiscoveryScan::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        return $scan->fresh();
    }

    public function markCompleted(AssetDiscoveryScan $scan, int $devicesFound): AssetDiscoveryScan
    {
        $scan->update([
            'status' => AssetDiscoveryScan::STATUS_COMPLETED,
            'devices_found' => $devicesFound,
            'completed_at' => now(),
        ]);

        return $scan->fresh();
    }

    public function markFailed(AssetDiscoveryScan $scan, string $message): AssetDiscoveryScan
    {
        $scan->update([
            'status' => AssetDiscoveryScan::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        return $scan->fresh();
    }

    public function createDevice(AssetDiscoveryScan $scan, array $data): AssetDiscoveryDevice
    {
        return $scan->devices()->create($data);
    }

    public function devicesForImport(AssetDiscoveryScan $scan, array $deviceIds): Collection
    {
        return AssetDiscoveryDevice::query()
            ->where('asset_discovery_scan_id', $scan->id)
            ->whereIn('id', $deviceIds)
            ->whereIn('status', [
                AssetDiscoveryDevice::STATUS_NEW,
                AssetDiscoveryDevice::STATUS_MATCHED,
            ])
            ->get();
    }
}
