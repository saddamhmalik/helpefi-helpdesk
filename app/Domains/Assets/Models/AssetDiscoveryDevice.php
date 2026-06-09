<?php

namespace App\Domains\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDiscoveryDevice extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'asset_discovery_scan_id',
        'ip_address',
        'mac_address',
        'hostname',
        'vendor',
        'status',
        'matched_asset_id',
        'imported_asset_id',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(AssetDiscoveryScan::class, 'asset_discovery_scan_id');
    }

    public function matchedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'matched_asset_id');
    }

    public function importedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'imported_asset_id');
    }
}
