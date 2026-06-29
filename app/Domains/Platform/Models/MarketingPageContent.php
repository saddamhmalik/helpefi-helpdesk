<?php

namespace App\Domains\Platform\Models;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPageContent extends Model
{
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    protected $connection = 'central';

    protected $table = 'marketing_page_content';

    protected $fillable = [
        'page_type',
        'slug',
        'content',
        'internal_links',
        'page_key',
        'status',
        'source_draft_id',
        'published_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'internal_links' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function sourceDraft(): BelongsTo
    {
        return $this->belongsTo(MarketingContentDraft::class, 'source_draft_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'updated_by');
    }
}
