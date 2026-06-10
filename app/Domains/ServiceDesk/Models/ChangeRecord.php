<?php

namespace App\Domains\ServiceDesk\Models;

use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRecord extends Model
{
    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RISK_CRITICAL = 'critical';

    protected $fillable = [
        'ticket_id',
        'risk',
        'impact',
        'rollback_plan',
        'planned_start',
        'planned_end',
        'cab_user_ids',
        'cab_notes',
        'implementation_notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_start' => 'datetime',
            'planned_end' => 'datetime',
            'cab_user_ids' => 'array',
        ];
    }

    public static function riskOptions(): array
    {
        return [
            self::RISK_LOW,
            self::RISK_MEDIUM,
            self::RISK_HIGH,
            self::RISK_CRITICAL,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
