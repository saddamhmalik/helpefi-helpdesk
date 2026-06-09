<?php

namespace App\Domains\Reports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SavedReport extends Model
{
    public const TYPE_TICKETS = 'tickets';

    public const TYPE_SLA_BREACHES = 'sla_breaches';

    public const TYPE_AGENT_PERFORMANCE = 'agent_performance';

    public const TYPE_CSAT = 'csat';

    public const TYPE_TIME_TRACKING = 'time_tracking';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'filters',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(ReportSchedule::class);
    }
}
