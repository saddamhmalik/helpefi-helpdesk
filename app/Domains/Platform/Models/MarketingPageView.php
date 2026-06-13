<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingPageView extends Model
{
    protected $connection = 'central';

    public $timestamps = false;

    protected $fillable = [
        'path',
        'referrer_host',
        'visitor_hash',
        'is_bot',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'visited_at' => 'datetime',
        ];
    }
}
