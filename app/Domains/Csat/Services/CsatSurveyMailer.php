<?php

namespace App\Domains\Csat\Services;

use App\Domains\Channels\Services\OutboundMailService;
use App\Domains\Tickets\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class CsatSurveyMailer
{
    public function __construct(
        private OutboundMailService $outboundMail,
    ) {
    }

    public function send(Ticket $ticket): void
    {
        $ticket->loadMissing(['contact', 'status']);

        if (! $ticket->contact?->email) {
            return;
        }

        $expiresAt = now()->addDays(30);
        $surveyUrl = $this->signedSurveyUrl($ticket, $expiresAt);
        $rateUrls = [];

        for ($rating = 1; $rating <= 5; $rating++) {
            $rateUrls[$rating] = $this->signedRateUrl($ticket, $rating, $expiresAt);
        }

        $this->outboundMail->deliverCsatSurvey(
            $ticket->contact->email,
            $ticket,
            $surveyUrl,
            $rateUrls,
        );
    }

    public function signedSurveyUrl(Ticket $ticket, ?Carbon $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'portal.csat.email.survey',
            $expiresAt ?? now()->addDays(30),
            [
                'ticket' => $ticket->id,
                'contact' => $ticket->contact_id,
            ],
        );
    }

    public function signedSubmitUrl(Ticket $ticket, ?Carbon $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'portal.csat.email.submit',
            $expiresAt ?? now()->addDays(30),
            [
                'ticket' => $ticket->id,
                'contact' => $ticket->contact_id,
            ],
        );
    }

    public function signedRateUrl(Ticket $ticket, int $rating, ?Carbon $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'portal.csat.email.rate',
            $expiresAt ?? now()->addDays(30),
            [
                'ticket' => $ticket->id,
                'contact' => $ticket->contact_id,
                'rating' => $rating,
            ],
        );
    }
}
