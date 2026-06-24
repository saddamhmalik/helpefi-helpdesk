<?php

namespace App\Domains\Tickets\Requests;

use App\Domains\Tickets\Support\TicketPeopleRules;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends TicketFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = array_merge([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => [
                'nullable',
                'exists:contacts,id',
                Rule::requiredIf(fn () => blank($this->input('requester_email'))),
            ],
            'assigned_to' => $this->assignableAgentRule(),
            'department_id' => ['nullable', 'exists:departments,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'ticket_status_id' => ['required', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['required', 'exists:ticket_priorities,id'],
            'type' => ['nullable', 'string', 'in:incident,service_request,change,problem'],
            'custom_fields' => ['nullable', 'array'],
        ], TicketPeopleRules::rules());

        $rules['requester_email'] = [
            'nullable',
            'email',
            'max:255',
            Rule::requiredIf(fn () => blank($this->input('contact_id'))),
        ];

        return $rules;
    }
}
