<?php

namespace App\Domains\Brands\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_default',
        'is_active',
        'portal_title',
        'primary_color',
        'accent_color',
        'ticket_number_prefix',
        'ticket_fields',
        'default_ticket_priority_id',
        'kb_deflection_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'ticket_fields' => 'array',
            'kb_deflection_enabled' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::query()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function defaultPriority(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tickets\Models\TicketPriority::class, 'default_ticket_priority_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(\App\Domains\Knowledge\Models\KnowledgeCollection::class);
    }

    public function inboxes(): HasMany
    {
        return $this->hasMany(\App\Domains\Channels\Models\EmailInbox::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(\App\Domains\Tickets\Models\Ticket::class);
    }
}
