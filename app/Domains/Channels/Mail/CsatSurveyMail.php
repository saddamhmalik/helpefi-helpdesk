<?php

namespace App\Domains\Channels\Mail;

use App\Domains\Channels\Models\EmailTemplate;
use App\Domains\Channels\Services\EmailTemplateService;
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
            subject: app(EmailTemplateService::class)->renderSubject(
                EmailTemplate::SLUG_CSAT_SURVEY,
                $this->templateVariables(),
                "How did we do on {$this->ticket->number}?",
            ),
        );
    }

    public function content(): Content
    {
        $rendered = app(EmailTemplateService::class)->render(
            EmailTemplate::SLUG_CSAT_SURVEY,
            $this->templateVariables(),
        );

        if ($rendered !== null) {
            return new Content(
                htmlString: app(EmailTemplateService::class)->wrapHtml($rendered['body_html']),
            );
        }

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

    private function templateVariables(): array
    {
        $links = collect($this->rateUrls)
            ->map(function (string $url, int $rating) {
                $stars = str_repeat('★', $rating).str_repeat('☆', 5 - $rating);

                return '<a href="'.e($url).'" style="display:inline-block;margin-right:8px;text-decoration:none;font-size:20px;">'.$stars.'</a>';
            })
            ->implode('');

        return [
            'ticket_number' => $this->ticket->number,
            'survey_url' => $this->surveyUrl,
            'rating_links' => $links,
        ];
    }
}
