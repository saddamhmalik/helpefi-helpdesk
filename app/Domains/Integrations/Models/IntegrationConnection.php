<?php

namespace App\Domains\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConnection extends Model
{
    public const PROVIDER_SLACK = 'slack';

    public const PROVIDER_JIRA = 'jira';

    public const PROVIDER_LINEAR = 'linear';

    public const PROVIDER_SHOPIFY = 'shopify';

    public const PROVIDER_HUBSPOT = 'hubspot';

    public const PROVIDER_SALESFORCE = 'salesforce';

    public const PROVIDER_MICROSOFT_TEAMS = 'microsoft_teams';

    public const PROVIDER_ZAPIER = 'zapier';

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
