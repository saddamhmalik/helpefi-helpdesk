<?php

namespace App\Domains\Tickets\Mail;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Ticket $ticket,
        private string $pdfContent,
        private string $fromAddress,
        private string $fromName,
        private bool $includeConversation,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: app(EmailTemplateService::class)->renderSubject(
                EmailTemplate::SLUG_TICKET_EXPORT,
                [
                    'ticket_number' => $this->ticket->number,
                    'ticket_subject' => $this->ticket->subject,
                ],
                "[{$this->ticket->number}] {$this->ticket->subject}",
            ),
        );
    }

    public function content(): Content
    {
        $rendered = app(EmailTemplateService::class)->render(
            EmailTemplate::SLUG_TICKET_EXPORT,
            [
                'ticket_number' => $this->ticket->number,
                'ticket_subject' => $this->ticket->subject,
            ],
        );

        if ($rendered !== null) {
            return new Content(
                htmlString: app(EmailTemplateService::class)->wrapHtml($rendered['body_html']),
            );
        }

        return new Content(
            text: 'mail.ticket-export',
            with: [
                'ticket' => $this->ticket,
                'includeConversation' => $this->includeConversation,
            ],
        );
    }

    public function attachments(): array
    {
        $filename = str_replace(['/', '\\'], '-', $this->ticket->number).'.pdf';

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
