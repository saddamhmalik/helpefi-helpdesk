<?php

namespace App\Domains\Tickets\Support;

class TicketAttachmentRules
{
    public const ALLOWED_MIMES = [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'zip', 'gz', 'tar',
    ];

    public const MAX_SIZE_KB = 10240;

    public const MAX_INBOUND_BASE64_LENGTH = 14000000;

    public static function fileRules(bool $required = true): array
    {
        $rules = [
            'file',
            'max:'.self::MAX_SIZE_KB,
            'mimes:'.implode(',', self::ALLOWED_MIMES),
        ];

        return $required ? array_merge(['required'], $rules) : $rules;
    }

    public static function attachmentArrayRules(): array
    {
        return [
            'nullable',
            'array',
            'max:5',
        ];
    }

    public static function attachmentItemRules(): array
    {
        return [
            'file',
            'max:'.self::MAX_SIZE_KB,
            'mimes:'.implode(',', self::ALLOWED_MIMES),
        ];
    }
}
