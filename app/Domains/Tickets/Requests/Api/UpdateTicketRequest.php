<?php

namespace App\Domains\Tickets\Requests\Api;

use App\Domains\Tickets\Requests\TicketFormRequest;
use App\Domains\Tickets\Support\TicketPeopleRules;

class UpdateTicketRequest extends TicketFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'assigned_to' => $this->assignableAgentRule(),
            'ticket_status_id' => ['sometimes', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['sometimes', 'exists:ticket_priorities,id'],
        ], TicketPeopleRules::rules());
    }
}
