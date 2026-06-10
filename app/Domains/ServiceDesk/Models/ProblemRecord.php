<?php

namespace App\Domains\ServiceDesk\Models;

use App\Domains\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProblemRecord extends Model
{
    protected $fillable = [
        'ticket_id',
        'root_cause',
        'workaround',
        'is_known_error',
    ];

    protected function casts(): array
    {
        return [
            'is_known_error' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function incidentLinks(): HasMany
    {
        return $this->hasMany(ProblemIncidentLink::class, 'problem_ticket_id', 'ticket_id');
    }
}
