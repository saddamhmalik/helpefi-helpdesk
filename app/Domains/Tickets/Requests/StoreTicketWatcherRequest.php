<?php

namespace App\Domains\Tickets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketWatcherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
