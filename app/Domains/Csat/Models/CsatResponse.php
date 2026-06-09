<?php

namespace App\Domains\Csat\Models;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsatResponse extends Model
{
    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_EMAIL = 'email';

    protected $fillable = [
        'ticket_id',
        'contact_id',
        'rating',
        'comment',
        'channel',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
