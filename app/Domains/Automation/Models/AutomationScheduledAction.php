<?php

namespace App\Domains\Automation\Models;

use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationScheduledAction extends Model
{
    protected $fillable = [
        'ticket_id',
        'automation_rule_id',
        'actions',
        'context',
        'run_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'context' => 'array',
            'run_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
