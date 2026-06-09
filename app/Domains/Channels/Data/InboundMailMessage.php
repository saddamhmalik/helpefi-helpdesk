<?php

namespace App\Domains\Channels\Data;

class InboundMailMessage
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $fromEmail,
        public readonly ?string $fromName,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly ?string $toEmail = null,
        public readonly ?string $pollUid = null,
        public readonly array $inReplyTo = [],
        public readonly array $references = [],
        public readonly array $attachments = [],
        public readonly array $ccEmails = [],
    ) {
    }

    public function toPayload(): array
    {
        return [
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
            'subject' => $this->subject,
            'body' => $this->body,
            'message_id' => $this->messageId,
            'to_email' => $this->toEmail,
            'in_reply_to' => $this->inReplyTo,
            'references' => $this->references,
            'cc_emails' => $this->ccEmails,
            'attachments' => array_map(
                fn (InboundMailAttachment $attachment) => [
                    'filename' => $attachment->filename,
                    'content' => $attachment->content,
                    'mime_type' => $attachment->mimeType,
                ],
                $this->attachments,
            ),
        ];
    }
}
