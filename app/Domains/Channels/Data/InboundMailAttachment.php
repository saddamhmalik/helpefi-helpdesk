<?php

namespace App\Domains\Channels\Data;

class InboundMailAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $content,
        public readonly ?string $mimeType = null,
    ) {
    }
}
