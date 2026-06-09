<?php

namespace App\Domains\Channels\Mail;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Ticket $ticket,
        private TicketMessage $ticketMessage,
        private User $agent,
        private string $fromAddress,
        private string $fromName,
        private string $replyMessageId,
        private array $replyToAddress = [],
    ) {
    }

    public function envelope(): Envelope
    {
        [$replyToAddress, $replyToName] = $this->replyToAddress ?: [$this->fromAddress, $this->fromName];

        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: "Re: [{$this->ticket->number}] {$this->ticket->subject}",
            replyTo: [new Address($replyToAddress, $replyToName)],
            using: [
                function (Email $email) {
                    $email->getHeaders()->addIdHeader('Message-ID', $this->replyMessageId);
                },
            ],
        );
    }

    public function content(): Content
    {
        $replyBody = $this->ticketMessage->body;
        $plainBody = \App\Domains\Tickets\Support\MessageBodySanitizer::toPlainText($replyBody);
        $isHtml = str_contains($replyBody, '<');

        return new Content(
            text: 'mail.ticket-reply',
            html: $isHtml ? 'mail.ticket-reply-html' : null,
            with: [
                'ticket' => $this->ticket,
                'replyBody' => $plainBody,
                'replyBodyHtml' => $isHtml ? $replyBody : nl2br(e($plainBody)),
                'agent' => $this->agent,
            ],
        );
    }

    public function attachments(): array
    {
        $this->ticketMessage->loadMissing('attachments');

        return $this->ticketMessage->attachments
            ->map(fn ($attachment) => Attachment::fromData(
                fn () => Storage::disk('public')->get($attachment->path),
                $attachment->filename,
            )->withMime($attachment->mime_type ?? 'application/octet-stream'))
            ->all();
    }
}
