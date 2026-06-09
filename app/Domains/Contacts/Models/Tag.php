<?php

namespace App\Domains\Contacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Tickets\Models\Ticket::class, 'ticket_tag');
    }
}
