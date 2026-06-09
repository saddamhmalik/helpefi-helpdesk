<?php

namespace App\Domains\Assets\Repositories;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetAssignmentLog;

class AssetAssignmentLogRepository
{
    public function record(Asset $asset, string $action, ?int $changedBy = null): void
    {
        AssetAssignmentLog::query()->create([
            'asset_id' => $asset->id,
            'contact_id' => $asset->contact_id,
            'organization_id' => $asset->organization_id,
            'changed_by' => $changedBy,
            'action' => $action,
        ]);
    }

    public function forAsset(int $assetId, int $limit = 20): array
    {
        return AssetAssignmentLog::query()
            ->with([
                'contact:id,name,email',
                'organization:id,name',
                'changedBy:id,name',
            ])
            ->where('asset_id', $assetId)
            ->latest()
            ->limit($limit)
            ->get()
            ->all();
    }
}
