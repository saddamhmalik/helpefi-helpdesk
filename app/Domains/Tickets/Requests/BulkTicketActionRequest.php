<?php

namespace App\Domains\Tickets\Requests;

use Illuminate\Validation\Rule;

class BulkTicketActionRequest extends TicketFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_ids' => ['required', 'array', 'min:1', 'max:100'],
            'ticket_ids.*' => ['integer', 'distinct', 'exists:tickets,id'],
            'action' => ['required', 'string', 'in:assign,status,priority,close,snooze'],
            'assigned_to' => $this->assignableAgentRule(),
            'ticket_status_id' => ['required_if:action,status', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['required_if:action,priority', 'exists:ticket_priorities,id'],
            'minutes' => ['exclude_unless:action,snooze', 'required_without:until', 'integer', 'min:15', 'max:10080'],
            'until' => ['exclude_unless:action,snooze', 'required_without:minutes', 'date', 'after:now'],
        ];
    }
}
