<?php

namespace App\Domains\Tenancy\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInfrastructureBackup extends Model
{
    use HasUuids;

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'object_key',
        'label',
        'size',
        'stored_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'stored_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
