<?php

namespace App\Domains\Assignment\Models;

use App\Domains\Tickets\Models\TicketPriority;
use App\Domains\Workforce\Models\Department;
use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentRule extends Model
{
    public const STRATEGY_ROUND_ROBIN = 'round_robin';

    public const STRATEGY_LOAD_BASED = 'load_based';

    protected $fillable = [
        'name',
        'strategy',
        'is_active',
        'sort_order',
        'team_id',
        'department_id',
        'channel_ids',
        'ticket_priority_id',
        'skill_ids',
        'last_assigned_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'channel_ids' => 'array',
            'skill_ids' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    public function lastAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_assigned_user_id');
    }
}
