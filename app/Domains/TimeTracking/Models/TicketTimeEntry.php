<?php

namespace App\Domains\TimeTracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTimeEntry extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'minutes',
        'note',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'logged_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tickets\Models\Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
