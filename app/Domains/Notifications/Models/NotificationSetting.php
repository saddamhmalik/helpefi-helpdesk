<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'email_enabled',
        'notify_ticket_assigned',
        'notify_customer_reply',
        'notify_sla_breach',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'notify_ticket_assigned' => 'boolean',
            'notify_customer_reply' => 'boolean',
            'notify_sla_breach' => 'boolean',
        ];
    }
}
