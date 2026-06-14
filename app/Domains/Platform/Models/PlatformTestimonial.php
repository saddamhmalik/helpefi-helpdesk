<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformTestimonial extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'quote',
        'name',
        'role',
        'company_type',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
