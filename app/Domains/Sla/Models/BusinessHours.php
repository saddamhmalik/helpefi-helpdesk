<?php

namespace App\Domains\Sla\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessHours extends Model
{
    protected $table = 'business_hours';

    protected $fillable = [
        'name',
        'timezone',
        'schedule',
    ];

    protected function casts(): array
    {
        return ['schedule' => 'array'];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }
}
