<?php

namespace App\Domains\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class CentralSetting extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'trial_days',
        'currency',
        'plan_pricing',
        'plan_catalog',
        'backup_schedule_enabled',
        'backup_schedule_frequency',
        'backup_schedule_weekday',
        'backup_schedule_time',
    ];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'plan_pricing' => 'array',
            'plan_catalog' => 'array',
            'backup_schedule_enabled' => 'boolean',
            'backup_schedule_weekday' => 'integer',
        ];
    }
}
