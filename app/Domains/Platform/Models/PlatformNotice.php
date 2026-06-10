<?php

namespace App\Domains\Platform\Models;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformNotice extends Model
{
    public const TYPE_GENERAL = 'general';

    public const TYPE_MAINTENANCE = 'maintenance';

    public const TYPE_OFFER = 'offer';

    public const TYPE_ANNOUNCEMENT = 'announcement';

    public const TARGET_ALL = 'all';

    public const TARGET_SELECTED = 'selected';

    public const AUDIENCE_ADMINS = 'admins';

    public const AUDIENCE_ALL_AGENTS = 'all_agents';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $connection = 'central';

    protected $fillable = [
        'title',
        'body_html',
        'image_path',
        'image_disk',
        'notice_type',
        'target_scope',
        'tenant_ids',
        'audience',
        'starts_at',
        'ends_at',
        'is_active',
        'dismissible',
        'priority',
        'status',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
            'dismissible' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function targetsTenant(string $tenantId): bool
    {
        if ($this->target_scope === self::TARGET_ALL) {
            return true;
        }

        return in_array($tenantId, $this->tenant_ids ?? [], true);
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active || $this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
