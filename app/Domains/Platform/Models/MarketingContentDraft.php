<?php

namespace App\Domains\Platform\Models;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketingContentDraft extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'central';

    protected $fillable = [
        'content_type',
        'slug',
        'title',
        'brief',
        'target_page_key',
        'status',
        'generated_content',
        'edited_content',
        'seo',
        'schema_markup',
        'internal_links',
        'duplicate_warnings',
        'content_fingerprint',
        'ai_source',
        'generated_at',
        'published_at',
        'published_reference',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'generated_content' => 'array',
            'edited_content' => 'array',
            'seo' => 'array',
            'schema_markup' => 'array',
            'internal_links' => 'array',
            'duplicate_warnings' => 'array',
            'published_reference' => 'array',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'updated_by');
    }

    public function publishedPage(): HasOne
    {
        return $this->hasOne(MarketingPageContent::class, 'source_draft_id');
    }

    public function effectiveContent(): array
    {
        return $this->edited_content ?? $this->generated_content ?? [];
    }
}
