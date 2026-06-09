<?php

namespace App\Domains\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConnection extends Model
{
    public const PROVIDER_SLACK = 'slack';

    public const PROVIDER_JIRA = 'jira';

    public const PROVIDER_LINEAR = 'linear';

    protected $fillable = [
        'provider',
        'config',
        'events',
        'is_active',
        'last_delivered_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }
}
