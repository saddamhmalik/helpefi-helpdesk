<?php

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDeflectionEvent extends Model
{
    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_WIDGET = 'widget';

    public const EVENT_QUERY = 'query';

    public const EVENT_ANSWER = 'answer';

    public const EVENT_HELPFUL = 'helpful';

    public const EVENT_NOT_HELPFUL = 'not_helpful';

    public const EVENT_TICKET_CREATED = 'ticket_created';

    protected $fillable = [
        'session_id',
        'channel',
        'event_type',
        'query',
        'article_id',
        'ticket_id',
        'source',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Knowledge\Models\KnowledgeArticle::class, 'article_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tickets\Models\Ticket::class);
    }
}
