<?php

namespace App\Domains\Automation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    public const TRIGGER_TICKET_CREATED = 'ticket.created';

    public const TRIGGER_TICKET_UPDATED = 'ticket.updated';

    public const TRIGGER_CUSTOMER_MESSAGE = 'ticket.customer_message';

    protected $fillable = [
        'name',
        'trigger',
        'conditions',
        'actions',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
