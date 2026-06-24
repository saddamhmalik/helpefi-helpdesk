<?php

namespace App\Domains\Tickets\Requests;

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
            'department_id' => ['nullable', 'exists:departments,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'ticket_status_id' => ['sometimes', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['sometimes', 'exists:ticket_priorities,id'],
            'custom_fields' => ['nullable', 'array'],
        ], TicketPeopleRules::rules());
    }
}
