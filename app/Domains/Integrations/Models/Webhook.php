<?php

namespace App\Domains\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    public const EVENT_TICKET_CREATED = 'ticket.created';

    public const EVENT_TICKET_UPDATED = 'ticket.updated';

    public const EVENT_CUSTOMER_MESSAGE = 'ticket.customer_message';

    public const EVENT_AUTOMATION = 'automation.triggered';

    public const EVENT_TEST = 'webhook.test';

    protected $fillable = [
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'last_delivered_at',
        'last_status_code',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }
}
