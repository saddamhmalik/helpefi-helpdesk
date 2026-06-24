<?php

namespace App\Domains\Tickets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_message_id' => ['required', 'exists:ticket_messages,id'],
            'subject' => ['nullable', 'string', 'max:255'],
        ];
    }
}
