<?php

namespace App\Domains\Sla\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    protected $fillable = [
        'name',
        'is_default',
        'business_hours_id',
        'team_id',
        'customer_tier',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function businessHours(): BelongsTo
    {
        return $this->belongsTo(BusinessHours::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Workforce\Models\Team::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SlaTarget::class);
    }
}
