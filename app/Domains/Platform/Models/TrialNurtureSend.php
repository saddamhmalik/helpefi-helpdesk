<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class TrialNurtureSend extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'template_slug',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
