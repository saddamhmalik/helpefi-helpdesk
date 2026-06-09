<?php

namespace App\Domains\Tickets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAgentRead extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_id',
        'last_read_message_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'last_read_message_id');
    }
}
