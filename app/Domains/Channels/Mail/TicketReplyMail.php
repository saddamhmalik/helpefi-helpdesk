<?php

namespace App\Domains\Channels\Mail;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
use App\Domains\Tenancy\Services\TenantStorageResolver;
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
            subject: app(EmailTemplateService::class)->renderSubject(
                EmailTemplate::SLUG_TICKET_REPLY,
                $this->templateVariables(''),
                "Re: [{$this->ticket->number}] {$this->ticket->subject}",
            ),
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
        $replyBodyHtml = $isHtml ? $replyBody : nl2br(e($plainBody));

        $rendered = app(EmailTemplateService::class)->render(
            EmailTemplate::SLUG_TICKET_REPLY,
            $this->templateVariables($replyBodyHtml),
        );

        if ($rendered !== null) {
            return new Content(
                htmlString: app(EmailTemplateService::class)->wrapHtml($rendered['body_html']),
            );
        }

        return new Content(
            text: 'mail.ticket-reply',
            html: $isHtml ? 'mail.ticket-reply-html' : null,
            with: [
                'ticket' => $this->ticket,
                'replyBody' => $plainBody,
                'replyBodyHtml' => $replyBodyHtml,
                'agent' => $this->agent,
            ],
        );
    }

    private function templateVariables(string $replyBodyHtml): array
    {
        return [
            'ticket_number' => $this->ticket->number,
            'ticket_subject' => $this->ticket->subject,
            'agent_name' => $this->agent->name,
            'reply_body' => $replyBodyHtml,
        ];
    }

    public function attachments(): array
    {
        $this->ticketMessage->loadMissing('attachments');

        return $this->ticketMessage->attachments
            ->map(fn ($attachment) => Attachment::fromData(
                fn () => app(TenantStorageResolver::class)->get($attachment->path, $attachment->storage_disk) ?? '',
                $attachment->filename,
            )->withMime($attachment->mime_type ?? 'application/octet-stream'))
            ->all();
    }
}
