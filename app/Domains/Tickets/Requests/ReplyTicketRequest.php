<?php

namespace App\Domains\Tickets\Requests;

use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Tickets\Support\TicketAttachmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string'],
            'is_internal' => ['boolean'],
            'attachments' => TicketAttachmentRules::attachmentArrayRules(),
            'attachments.*' => TicketAttachmentRules::attachmentItemRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (MessageBodySanitizer::isEmpty($this->input('body', '')) && ! $this->hasFile('attachments')) {
                $validator->errors()->add('body', 'Add a message or attach at least one file.');
            }
        });
    }
}
