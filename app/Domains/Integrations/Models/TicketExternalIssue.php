<?php

namespace App\Domains\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketExternalIssue extends Model
{
    protected $fillable = [
        'ticket_id',
        'provider',
        'external_id',
        'external_key',
        'external_url',
        'status',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tickets\Models\Ticket::class);
    }
}
