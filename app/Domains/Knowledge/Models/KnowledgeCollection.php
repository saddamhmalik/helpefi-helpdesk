<?php

namespace App\Domains\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCollection extends Model
{
    protected $fillable = [
        'brand_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Brands\Models\Brand::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class);
    }
}
