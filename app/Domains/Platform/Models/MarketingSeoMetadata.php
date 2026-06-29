<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingSeoMetadata extends Model
{
    protected $connection = 'central';

    protected $table = 'marketing_seo_metadata';

    protected $fillable = [
        'page_key',
        'manual_seo_title',
        'manual_meta_description',
        'manual_keywords',
        'manual_og_description',
        'manual_twitter_description',
        'ai_seo_title',
        'ai_meta_description',
        'ai_keywords',
        'ai_og_description',
        'ai_twitter_description',
        'ai_slug_suggestions',
        'source_content',
        'ai_source',
        'ai_generated_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'ai_slug_suggestions' => 'array',
            'ai_generated_at' => 'datetime',
        ];
    }
}

