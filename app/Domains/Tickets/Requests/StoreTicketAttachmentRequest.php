<?php

namespace App\Domains\Tickets\Requests;

use App\Domains\Tickets\Support\TicketAttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => TicketAttachmentRules::fileRules(),
        ];
    }
}
