<?php

namespace App\Domains\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCategory extends Model
{
    protected $fillable = ['name', 'slug'];

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class);
    }
}
