<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformEmailTemplate extends Model
{
    protected $connection = 'central';

    public const SLUG_REGISTRATION = 'registration_confirmation';

    public const SLUG_WORKSPACE_WELCOME = 'workspace_welcome';

    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body_html',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
