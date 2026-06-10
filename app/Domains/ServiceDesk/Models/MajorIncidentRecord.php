<?php

namespace App\Domains\ServiceDesk\Models;

use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MajorIncidentRecord extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'ticket_id',
        'status',
        'declared_by_user_id',
        'declared_at',
        'resolved_by_user_id',
        'resolved_at',
        'coordinator_user_ids',
        'war_room_notes',
        'summary',
        'timeline',
        'lessons_learned',
        'action_items',
        'review_completed_at',
        'review_completed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'declared_at' => 'datetime',
            'resolved_at' => 'datetime',
            'review_completed_at' => 'datetime',
            'coordinator_user_ids' => 'array',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function reviewCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_completed_by_user_id');
    }
}
