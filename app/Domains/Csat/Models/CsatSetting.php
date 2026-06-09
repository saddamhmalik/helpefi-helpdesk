<?php

namespace App\Domains\Csat\Models;

use Illuminate\Database\Eloquent\Model;

class CsatSetting extends Model
{
    protected $fillable = [
        'enabled',
        'comment_required',
        'email_enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'comment_required' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }
}
