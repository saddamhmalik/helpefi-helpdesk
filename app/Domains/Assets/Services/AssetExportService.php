<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Repositories\AssetRepository;
use App\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetExportService
{
    public function __construct(private AssetRepository $assets)
    {
    }

    public function csv(array $filters): StreamedResponse
    {
        return CsvStream::download(CsvStream::timestampedFilename('assets'), function ($handle) use ($filters) {
            fputcsv($handle, [
                'Asset tag',
                'Name',
                'Type',
                'Status',
                'Serial number',
                'Assigned contact',
                'Contact email',
                'Organization',
                'Location',
                'IP address',
                'MAC address',
                'Hostname',
                'Manufacturer',
                'Model',
                'Vendor',
                'Purchase cost',
                'Purchased',
                'Warranty expires',
                'Last seen',
                'Discovery source',
                'Notes',
            ]);

            $this->assets->exportRows($filters, function ($asset) use ($handle) {
                fputcsv($handle, [
                    $asset->asset_tag,
                    $asset->name,
                    $asset->type?->name,
                    $asset->status,
                    $asset->serial_number,
                    $asset->contact?->name,
                    $asset->contact?->email,
                    $asset->organization?->name,
                    $asset->location,
                    $asset->ip_address,
                    $asset->mac_address,
                    $asset->hostname,
                    $asset->manufacturer,
                    $asset->model,
                    $asset->vendor,
                    $asset->purchase_cost,
                    $asset->purchased_at?->toDateString(),
                    $asset->warranty_expires_at?->toDateString(),
                    $asset->last_seen_at?->toDateTimeString(),
                    $asset->discovery_source,
                    $asset->notes,
                ]);
            });
        });
    }
}
