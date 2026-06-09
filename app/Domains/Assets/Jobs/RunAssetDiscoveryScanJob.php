<?php

namespace App\Domains\Assets\Jobs;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetDiscoveryDevice;
use App\Domains\Assets\Models\AssetDiscoveryScan;
use App\Domains\Assets\Repositories\AssetDiscoveryRepository;
use App\Domains\Assets\Support\NetworkDiscoveryScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunAssetDiscoveryScanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(public int $scanId)
    {
    }

    public function handle(
        AssetDiscoveryRepository $discovery,
        NetworkDiscoveryScanner $scanner,
    ): void {
        $scan = $discovery->findScan($this->scanId);
        $discovery->markRunning($scan);

        try {
            $ips = $scanner->expandSubnet($scan->subnet);
            $found = 0;

            foreach ($ips as $ip) {
                $probe = $scanner->probe($ip);

                if (! $probe['reachable']) {
                    continue;
                }

                $matchedAssetId = Asset::query()->where('ip_address', $ip)->value('id');
                $status = $matchedAssetId
                    ? AssetDiscoveryDevice::STATUS_MATCHED
                    : AssetDiscoveryDevice::STATUS_NEW;

                $discovery->createDevice($scan, [
                    'ip_address' => $ip,
                    'hostname' => $probe['hostname'],
                    'mac_address' => $probe['mac_address'],
                    'vendor' => $probe['vendor'],
                    'status' => $status,
                    'matched_asset_id' => $matchedAssetId,
                ]);

                $found++;
            }

            $discovery->markCompleted($scan, $found);
        } catch (Throwable $exception) {
            $discovery->markFailed($scan, $exception->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $discovery = app(AssetDiscoveryRepository::class);

        try {
            $scan = $discovery->findScan($this->scanId);

            if (! in_array($scan->status, [
                AssetDiscoveryScan::STATUS_PENDING,
                AssetDiscoveryScan::STATUS_RUNNING,
            ], true)) {
                return;
            }

            $discovery->markFailed(
                $scan,
                $exception?->getMessage() ?? 'Scan could not be completed. Please try again.'
            );
        } catch (Throwable) {
        }
    }
}
