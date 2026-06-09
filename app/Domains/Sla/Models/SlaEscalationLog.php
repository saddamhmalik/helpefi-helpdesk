<?php

namespace App\Domains\Sla\Models;

use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaEscalationLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'ticket_sla_timer_id',
        'level',
        'breach_type',
        'actions_taken',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'actions_taken' => 'array',
            'triggered_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function timer(): BelongsTo
    {
        return $this->belongsTo(TicketSlaTimer::class, 'ticket_sla_timer_id');
    }
}
