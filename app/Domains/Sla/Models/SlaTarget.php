<?php

namespace App\Domains\Sla\Models;

use App\Domains\Tickets\Models\TicketPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaTarget extends Model
{
    protected $fillable = [
        'sla_policy_id',
        'ticket_priority_id',
        'first_response_minutes',
        'resolution_minutes',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }
}
