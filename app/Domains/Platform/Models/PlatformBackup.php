<?php

namespace App\Domains\Platform\Models;

use App\Models\PlatformUser;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformBackup extends Model
{
    public const SCOPE_CENTRAL = 'central';

    public const SCOPE_TENANT = 'tenant';

    public const SCOPE_ALL_TENANTS = 'all_tenants';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $connection = 'central';

    protected $fillable = [
        'scope',
        'tenant_id',
        'status',
        'storage_disk',
        'path',
        'size_bytes',
        'checksum',
        'created_by',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }
}
