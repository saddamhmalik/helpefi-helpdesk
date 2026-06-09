<?php

namespace App\Domains\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetType extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
