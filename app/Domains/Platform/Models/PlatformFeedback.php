<?php

namespace App\Domains\Platform\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformFeedback extends Model
{
    public const TYPE_FEEDBACK = 'feedback';

    public const TYPE_FEATURE_REQUEST = 'feature_request';

    public const STATUS_OPEN = 'open';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_CLOSED = 'closed';

    protected $connection = 'central';

    protected $table = 'platform_feedback';

    protected $fillable = [
        'tenant_id',
        'tenant_name',
        'user_id',
        'user_name',
        'user_email',
        'type',
        'subject',
        'body',
        'status',
        'ip_address',
        'user_agent',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
