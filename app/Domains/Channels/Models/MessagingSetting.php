<?php

namespace App\Domains\Channels\Models;

use Illuminate\Database\Eloquent\Model;

class MessagingSetting extends Model
{
    protected $fillable = [
        'is_active',
        'account_sid',
        'auth_token',
        'whatsapp_from',
        'sms_from',
        'webhook_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auth_token' => 'encrypted',
        ];
    }
}
