<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    protected $fillable = [
        'mfa_required_for_agents',
        'audit_retention_days',
        'closed_ticket_retention_days',
        'sso_enabled',
        'sso_protocol',
        'sso_config',
    ];

    protected function casts(): array
    {
        return [
            'mfa_required_for_agents' => 'boolean',
            'audit_retention_days' => 'integer',
            'closed_ticket_retention_days' => 'integer',
            'sso_enabled' => 'boolean',
            'sso_config' => 'encrypted:array',
        ];
    }
}
