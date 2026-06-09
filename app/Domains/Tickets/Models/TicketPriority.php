<?php

namespace App\Domains\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPriority extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
