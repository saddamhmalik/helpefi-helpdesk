<?php

namespace App\Domains\Platform\Models;

use App\Models\PlatformUser;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $fillable = [
        'platform_user_id',
        'actor_email',
        'tenant_id',
        'event',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'properties',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'platform_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
