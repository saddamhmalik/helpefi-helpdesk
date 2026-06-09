<?php

namespace App\Domains\Assets\Models;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    public const STATUS_IN_USE = 'in_use';

    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'asset_type_id',
        'parent_id',
        'asset_tag',
        'name',
        'serial_number',
        'status',
        'contact_id',
        'organization_id',
        'location',
        'ip_address',
        'mac_address',
        'hostname',
        'purchased_at',
        'warranty_expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'warranty_expires_at' => 'date',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class)->withTimestamps();
    }
}
