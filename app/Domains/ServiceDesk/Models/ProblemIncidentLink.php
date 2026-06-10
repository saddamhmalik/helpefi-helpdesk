<?php

namespace App\Domains\ServiceDesk\Models;

use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemIncidentLink extends Model
{
    protected $fillable = [
        'problem_ticket_id',
        'incident_ticket_id',
        'linked_by_user_id',
    ];

    public function problemTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'problem_ticket_id');
    }

    public function incidentTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'incident_ticket_id');
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }
}
