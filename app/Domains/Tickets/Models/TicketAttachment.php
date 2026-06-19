<?php

namespace App\Domains\Tickets\Models;

use App\Domains\Tenancy\Services\TenantStorageResolver;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $appends = ['url'];

    protected $fillable = [
        'ticket_id',
        'ticket_message_id',
        'user_id',
        'filename',
        'path',
        'storage_disk',
        'mime_type',
        'size',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return app(TenantStorageResolver::class)->url($this->path, $this->storage_disk);
    }
}
