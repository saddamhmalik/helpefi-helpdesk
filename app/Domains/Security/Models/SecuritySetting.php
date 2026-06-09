<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    protected $fillable = [
        'mfa_required_for_agents',
        'audit_retention_days',
        'closed_ticket_retention_days',
    ];

    protected function casts(): array
    {
        return [
            'mfa_required_for_agents' => 'boolean',
            'audit_retention_days' => 'integer',
            'closed_ticket_retention_days' => 'integer',
        ];
    }
}
