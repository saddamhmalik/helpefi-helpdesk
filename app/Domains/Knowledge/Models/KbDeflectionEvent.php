<?php

namespace App\Domains\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbDeflectionEvent extends Model
{
    public const EVENT_SUGGESTIONS_SHOWN = 'suggestions_shown';

    public const EVENT_ARTICLE_CLICKED = 'article_clicked';

    public const EVENT_DEFLECTED = 'deflected';

    public const EVENT_CONTINUED = 'continued';

    public const EVENT_TICKET_CREATED = 'ticket_created';

    protected $fillable = [
        'session_id',
        'event_type',
        'query',
        'article_id',
        'ticket_id',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'article_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tickets\Models\Ticket::class);
    }
}
