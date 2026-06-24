<?php

namespace App\Domains\Tickets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_ticket_id' => ['required', 'exists:tickets,id'],
            'import_conversation' => ['boolean'],
        ];
    }
}
