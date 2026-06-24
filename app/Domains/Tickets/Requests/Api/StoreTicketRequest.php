<?php

namespace App\Domains\Tickets\Requests\Api;

use App\Domains\Tickets\Requests\TicketFormRequest;
use App\Domains\Tickets\Support\TicketPeopleRules;

class StoreTicketRequest extends TicketFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'assigned_to' => $this->assignableAgentRule(),
            'ticket_status_id' => ['required', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['required', 'exists:ticket_priorities,id'],
        ], TicketPeopleRules::rules());
    }
}
