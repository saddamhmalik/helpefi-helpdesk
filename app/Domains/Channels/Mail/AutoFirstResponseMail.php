<?php

namespace App\Domains\Channels\Mail;

use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class AutoFirstResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Ticket $ticket,
        private TicketMessage $ticketMessage,
        private string $fromAddress,
        private string $fromName,
        private string $replyMessageId,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: "Re: [{$this->ticket->number}] {$this->ticket->subject}",
            replyTo: [new Address($this->fromAddress, $this->fromName)],
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
            text: 'mail.auto-first-response',
            html: $isHtml ? 'mail.auto-first-response-html' : null,
            with: [
                'ticket' => $this->ticket,
                'replyBody' => $plainBody,
                'replyBodyHtml' => $isHtml ? $replyBody : nl2br(e($plainBody)),
            ],
        );
    }
}
