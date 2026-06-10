<?php

namespace App\Domains\Channels\Mail;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketMessage;
use App\Domains\Tickets\Support\MessageBodySanitizer;
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
        private TicketMessage $customerMessage,
        private string $fromAddress,
        private string $fromName,
        private string $replyMessageId,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: app(EmailTemplateService::class)->renderSubject(
                EmailTemplate::SLUG_AUTO_FIRST_RESPONSE,
                $this->baseVariables(),
                "Re: [{$this->ticket->number}] {$this->ticket->subject}",
            ),
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
        $plainBody = MessageBodySanitizer::toPlainText($replyBody);
        $isHtml = str_contains($replyBody, '<');
        $replyBodyHtml = $isHtml ? $replyBody : nl2br(e($plainBody));

        $originalBody = $this->customerMessage->body;
        $originalPlainBody = MessageBodySanitizer::toPlainText($originalBody);
        $originalIsHtml = str_contains($originalBody, '<');
        $originalBodyHtml = $originalIsHtml ? $originalBody : nl2br(e($originalPlainBody));

        $rendered = app(EmailTemplateService::class)->render(
            EmailTemplate::SLUG_AUTO_FIRST_RESPONSE,
            array_merge($this->baseVariables(), [
                'reply_body' => $replyBodyHtml,
                'original_message_body' => $originalBodyHtml,
            ]),
        );

        if ($rendered !== null) {
            return new Content(
                htmlString: app(EmailTemplateService::class)->wrapHtml($rendered['body_html']),
            );
        }

        return new Content(
            text: 'mail.auto-first-response',
            html: $isHtml || $originalIsHtml ? 'mail.auto-first-response-html' : null,
            with: [
                'ticket' => $this->ticket,
                'replyBody' => $plainBody,
                'replyBodyHtml' => $replyBodyHtml,
                'originalMessageBody' => $originalPlainBody,
                'originalMessageBodyHtml' => $originalBodyHtml,
            ],
        );
    }

    private function baseVariables(): array
    {
        return [
            'ticket_number' => $this->ticket->number,
            'ticket_subject' => $this->ticket->subject,
        ];
    }
}
