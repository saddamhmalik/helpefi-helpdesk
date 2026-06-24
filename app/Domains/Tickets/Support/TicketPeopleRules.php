<?php

namespace App\Domains\Tickets\Support;

use App\Domains\Tickets\Services\TicketCcService;

class TicketPeopleRules
{
    public static function rules(): array
    {
        return [
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'cc_emails' => ['nullable', 'array', 'max:'.TicketCcService::MAX_CC],
            'cc_emails.*' => ['email', 'max:255'],
        ];
    }
}
