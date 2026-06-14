<?php

namespace App\Domains\Knowledge\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KnowledgeArticle extends Model
{
    protected $fillable = [
        'knowledge_category_id',
        'knowledge_collection_id',
        'author_id',
        'title',
        'slug',
        'locale',
        'translation_group_id',
        'excerpt',
        'body',
        'is_published',
        'is_public',
        'is_system',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'is_system' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'knowledge_category_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCollection::class, 'knowledge_collection_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeArticleVersion::class)->orderByDesc('version_number');
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(KnowledgeArticleEmbedding::class);
    }
}
