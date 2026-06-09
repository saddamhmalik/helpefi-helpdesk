<?php

namespace App\Domains\Channels\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    public const TYPE_EMAIL = 'email';

    public const TYPE_WEB = 'web';

    public const TYPE_PORTAL = 'portal';

    public const TYPE_API = 'api';

    public const TYPE_CHAT = 'chat';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
