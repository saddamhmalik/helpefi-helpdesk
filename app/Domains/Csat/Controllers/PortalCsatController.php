<?php

namespace App\Domains\Csat\Controllers;

use App\Domains\Brands\Models\Brand;
use App\Domains\Csat\Models\CsatResponse;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Csat\Services\CsatSurveyMailer;
use App\Domains\Knowledge\Services\PortalService;
use App\Domains\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalCsatController extends Controller
{
    public function __construct(
        private CsatService $csat,
        private PortalService $portal,
        private CsatSurveyMailer $mailer,
    ) {
    }

    public function submitAuthenticated(Request $request, string $ticket): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticketModel = $this->portal->customerTicket($request->user(), (int) $ticket);
        $contact = $request->user()->contact;

        $this->csat->submit($ticketModel, $contact, $data['rating'], $data['comment'] ?? null);

        return back()->with('success', 'Thank you for your feedback.');
    }

    public function submitGuest(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket = $this->portal->trackTicket($data['number'], $data['email']);

        if (! $ticket || ! $ticket->contact) {
            return back()->withErrors(['number' => 'No ticket found for that number and email.']);
        }

        $this->csat->submit($ticket, $ticket->contact, $data['rating'], $data['comment'] ?? null);

        return redirect()->route('portal.track', [
            'brand' => $brand,
            'number' => $data['number'],
            'email' => $data['email'],
        ])->with('success', 'Thank you for your feedback.');
    }

    public function showEmailSurvey(Request $request, string $ticket): Response
    {
        $ticketModel = $this->resolveEmailSurveyTicket($request, (int) $ticket);

        return Inertia::render('Portal/CsatSurvey', [
            'ticket' => [
                'id' => $ticketModel->id,
                'number' => $ticketModel->number,
                'subject' => $ticketModel->subject,
            ],
            'csat' => $this->csat->promptForTicket($ticketModel),
            'submitUrl' => $this->mailer->signedSubmitUrl($ticketModel),
        ]);
    }

    public function submitEmailSurvey(Request $request, string $ticket): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticketModel = $this->resolveEmailSurveyTicket($request, (int) $ticket);

        $this->csat->submit(
            $ticketModel,
            $ticketModel->contact,
            $data['rating'],
            $data['comment'] ?? null,
            CsatResponse::CHANNEL_EMAIL,
        );

        return redirect($this->mailer->signedSurveyUrl($ticketModel))
            ->with('success', 'Thank you for your feedback.');
    }

    public function quickEmailRate(Request $request, string $ticket, string $rating): RedirectResponse
    {
        $ratingValue = (int) $rating;
        abort_unless($ratingValue >= 1 && $ratingValue <= 5, 404);

        $ticketModel = $this->resolveEmailSurveyTicket($request, (int) $ticket);

        $this->csat->submit(
            $ticketModel,
            $ticketModel->contact,
            $ratingValue,
            null,
            CsatResponse::CHANNEL_EMAIL,
        );

        return redirect($this->mailer->signedSurveyUrl($ticketModel))
            ->with('success', 'Thank you for your feedback.');
    }

    private function resolveEmailSurveyTicket(Request $request, int $ticket): Ticket
    {
        $contactId = (int) $request->query('contact', $request->input('contact'));

        abort_unless($contactId > 0, 403);

        $ticketModel = Ticket::query()
            ->with(['contact', 'status', 'csatResponse'])
            ->findOrFail($ticket);

        abort_unless($contactId === (int) $ticketModel->contact_id && $ticketModel->contact, 403);

        return $ticketModel;
    }
}
