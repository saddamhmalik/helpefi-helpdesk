<?php

namespace App\Domains\Chat\Models;

use App\Domains\Channels\Models\Channel;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSession extends Model
{
    protected $fillable = [
        'uuid',
        'channel_id',
        'contact_id',
        'ticket_id',
        'token',
        'visitor_name',
        'page_url',
        'user_agent',
        'last_seen_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
