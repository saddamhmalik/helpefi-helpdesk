<?php

namespace App\Domains\Platform\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSlowQuery extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'connection',
        'database_host',
        'database_name',
        'sql',
        'time_ms',
        'bindings',
        'method',
        'url',
        'route_name',
        'source_file',
        'source_line',
        'source_callable',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'bindings' => 'array',
            'time_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
