<?php

namespace App\Domains\Channels\Mail;

use App\Domains\SideConversations\Models\SideConversation;
use App\Domains\SideConversations\Models\SideConversationMessage;
use App\Domains\SideConversations\Services\SideConversationThreadService;
use App\Domains\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class SideConversationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Ticket $ticket,
        private SideConversation $conversation,
        private SideConversationMessage $message,
        private User $agent,
        private string $fromAddress,
        private string $fromName,
        private string $replyMessageId,
        private bool $isReply = true,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: SideConversationThreadService::emailSubject($this->ticket, $this->conversation, $this->isReply),
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
        $replyBody = $this->message->body;
        $plainBody = \App\Domains\Tickets\Support\MessageBodySanitizer::toPlainText($replyBody);
        $isHtml = str_contains($replyBody, '<');

        return new Content(
            text: 'mail.side-conversation-reply',
            html: $isHtml ? 'mail.side-conversation-reply-html' : null,
            with: [
                'ticket' => $this->ticket,
                'conversation' => $this->conversation,
                'replyBody' => $plainBody,
                'replyBodyHtml' => $isHtml ? $replyBody : nl2br(e($plainBody)),
                'agent' => $this->agent,
            ],
        );
    }
}
