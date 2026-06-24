<?php

namespace App\Domains\Tickets\Models;

use App\Domains\Brands\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketNumberSequence extends Model
{
    protected $fillable = [
        'brand_id',
        'last_value',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
