<?php

namespace App\Domains\Channels\Mail;

use App\Domains\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CsatSurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Ticket $ticket,
        private string $surveyUrl,
        private array $rateUrls,
        private string $fromAddress,
        private string $fromName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: "How did we do on {$this->ticket->number}?",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.csat-survey',
            html: 'mail.csat-survey-html',
            with: [
                'ticket' => $this->ticket,
                'surveyUrl' => $this->surveyUrl,
                'rateUrls' => $this->rateUrls,
            ],
        );
    }
}
