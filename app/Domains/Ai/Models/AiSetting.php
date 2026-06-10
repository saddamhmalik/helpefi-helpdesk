<?php

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $fillable = [
        'enabled',
        'model',
        'deflection_enabled',
        'deflection_portal_enabled',
        'deflection_widget_enabled',
        'triage_enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'deflection_enabled' => 'boolean',
            'deflection_portal_enabled' => 'boolean',
            'deflection_widget_enabled' => 'boolean',
            'triage_enabled' => 'boolean',
        ];
    }
}
