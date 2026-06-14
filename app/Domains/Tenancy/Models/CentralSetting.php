<?php

namespace App\Domains\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class CentralSetting extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'trial_days',
        'tenant_purge_grace_days',
        'tenant_purge_enabled',
        'currency',
        'india_pricing',
        'social_links',
        'testimonials_enabled',
        'plan_pricing',
        'plan_catalog',
        'addon_catalog',
        'backup_schedule_enabled',
        'backup_schedule_frequency',
        'backup_schedule_weekday',
        'backup_schedule_time',
    ];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'tenant_purge_grace_days' => 'integer',
            'tenant_purge_enabled' => 'boolean',
            'india_pricing' => 'boolean',
            'social_links' => 'array',
            'plan_pricing' => 'array',
            'plan_catalog' => 'array',
            'addon_catalog' => 'array',
            'backup_schedule_enabled' => 'boolean',
            'backup_schedule_weekday' => 'integer',
            'testimonials_enabled' => 'boolean',
        ];
    }
}
