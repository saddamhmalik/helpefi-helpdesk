<?php

namespace App\Domains\Channels\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    public const SLUG_TICKET_REPLY = 'ticket_reply';

    public const SLUG_AUTO_FIRST_RESPONSE = 'auto_first_response';

    public const SLUG_TEAM_INVITATION = 'team_invitation';

    public const SLUG_CSAT_SURVEY = 'csat_survey';

    public const SLUG_TICKET_EXPORT = 'ticket_export';

    public const SLUG_SCHEDULED_REPORT = 'scheduled_report';

    public const SLUG_SIDE_CONVERSATION = 'side_conversation';

    public const SLUG_TICKET_ASSIGNED = 'ticket_assigned';

    public const SLUG_CUSTOMER_REPLY = 'customer_reply';

    public const SLUG_SLA_BREACH = 'sla_breach';

    public const SLUG_APPROVAL_REQUESTED = 'approval_requested';

    public const SLUG_APPROVAL_DECIDED = 'approval_decided';

    protected $fillable = [
        'slug',
        'name',
        'subject',
        'body_html',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
