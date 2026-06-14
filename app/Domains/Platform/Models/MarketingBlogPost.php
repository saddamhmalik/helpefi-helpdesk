<?php

namespace App\Domains\Platform\Models;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingBlogPost extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $connection = 'central';

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body',
        'status',
        'published_at',
        'reading_minutes',
        'related_slugs',
        'og_image_url',
        'seo_title',
        'seo_description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'related_slugs' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
