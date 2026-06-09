<?php

namespace App\Domains\Contacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'website',
        'phone',
        'description',
        'customer_tier',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(OrganizationDomain::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
