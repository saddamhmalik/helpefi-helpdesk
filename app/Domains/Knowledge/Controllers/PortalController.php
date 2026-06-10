<?php

namespace App\Domains\Knowledge\Controllers;

use App\Domains\Brands\Models\Brand;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Knowledge\Services\KbDeflectionService;
use App\Domains\Knowledge\Services\PortalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function __construct(
        private PortalService $portalService,
        private CsatService $csatService,
        private KbDeflectionService $kbDeflection,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Portal/Index', $this->portalService->home());
    }

    public function collection(\App\Domains\Brands\Models\Brand $brand, string $collectionSlug): Response
    {
        $data = $this->portalService->collection($collectionSlug);

        return Inertia::render('Portal/Collection', $data);
    }

    public function article(\App\Domains\Brands\Models\Brand $brand, string $articleSlug): Response
    {
        return Inertia::render('Portal/Article', $this->portalService->article($articleSlug));
    }

    public function search(Request $request): Response
    {
        return Inertia::render('Portal/Search', $this->portalService->search($request->query('q')));
    }

    public function showSubmit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Portal/Submit', [
            'customer' => $user?->hasRole('customer') ? [
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'kbDeflectionEnabled' => $this->kbDeflection->isEnabled(),
            'ticketFields' => $this->portalService->ticketFieldDefinitions(),
        ]);
    }

    public function submit(Request $request, Brand $brand): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'kb_session_id' => ['nullable', 'uuid'],
            'custom_fields' => ['nullable', 'array'],
        ];

        if (! $user?->hasRole('customer')) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        $data = $request->validate($rules);

        if ($user?->hasRole('customer')) {
            $data['name'] = $user->name;
            $data['email'] = $user->email;
        }

        $ticket = $this->portalService->submitTicket($data, $user);

        if (! empty($data['kb_session_id'])) {
            $this->kbDeflection->recordTicketCreated(
                $data['kb_session_id'],
                $ticket->id,
                trim($data['subject'].' '.strip_tags((string) ($data['description'] ?? ''))),
            );
        }

        if ($user?->hasRole('customer')) {
            return redirect()
                ->route('portal.my-tickets.show', ['brand' => $brand, 'ticket' => $ticket->id])
                ->with('success', 'Your request was submitted.');
        }

        return redirect()
            ->route('portal.track', ['brand' => $brand, 'number' => $ticket->number, 'email' => $data['email']])
            ->with('success', 'Your request was submitted.');
    }

    public function showTrack(Request $request): Response
    {
        $number = $request->query('number');
        $email = $request->query('email');
        $ticket = null;

        if ($number && $email) {
            $ticket = $this->portalService->trackTicket($number, $email);
        }

        return Inertia::render('Portal/Track', [
            'number' => $number,
            'email' => $email,
            'ticket' => $ticket,
            'csat' => $ticket ? $this->csatService->promptForTicket($ticket) : null,
        ]);
    }

    public function track(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        return redirect()->route('portal.track', array_merge(['brand' => $brand], $data));
    }

    public function myTickets(Request $request): Response
    {
        return Inertia::render('Portal/MyTickets', [
            'tickets' => $this->portalService->ticketsForUser($request->user()),
        ]);
    }

    public function myTicket(Request $request, string $ticket): Response
    {
        $ticketModel = $this->portalService->customerTicket($request->user(), (int) $ticket);

        return Inertia::render('Portal/MyTicket', [
            'ticket' => $ticketModel,
            'csat' => $this->csatService->promptForTicket($ticketModel),
        ]);
    }
}
