<?php

namespace App\Domains\Sla\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaEscalationRule extends Model
{
    public const BREACH_FIRST_RESPONSE = 'first_response';

    public const BREACH_RESOLUTION = 'resolution';

    protected $fillable = [
        'sla_policy_id',
        'level',
        'breach_type',
        'delay_minutes_after_breach',
        'actions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }
}
