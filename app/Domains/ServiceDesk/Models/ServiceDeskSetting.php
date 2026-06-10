<?php

namespace App\Domains\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceDeskSetting extends Model
{
    protected $fillable = [
        'change_requires_approval',
        'change_approver_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'change_requires_approval' => 'boolean',
            'change_approver_user_ids' => 'array',
        ];
    }
}
