<?php

namespace App\Domains\ServiceDesk\Controllers;

use App\Domains\ServiceDesk\Services\MajorIncidentService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Domains\Tickets\Services\TicketShowPageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MajorIncidentController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private MajorIncidentService $majorIncidents,
        private TicketShowPageService $ticketShowPage,
    ) {
    }

    public function index(): Response
    {
        if (! $this->serviceDesk->isAvailable()) {
            return Inertia::render('ServiceDesk/Upgrade', $this->serviceDesk->upgradeContext());
        }

        return Inertia::render('ServiceDesk/MajorIncidents/Index', $this->majorIncidents->index());
    }

    public function warRoom(int $ticket, Request $request): Response|RedirectResponse
    {
        if (! $this->serviceDesk->isAvailable()) {
            return Inertia::render('ServiceDesk/Upgrade', $this->serviceDesk->upgradeContext());
        }

        $record = $this->majorIncidents->warRoomSnapshot($ticket);

        if ($record === null) {
            return redirect()
                ->route('service-desk.major-incidents.index')
                ->with('error', 'No war room exists for this ticket. Declare a major incident on an incident ticket first.');
        }

        $userId = $request->user()->id;

        return Inertia::render('ServiceDesk/MajorIncidents/WarRoom', array_merge(
            ['majorIncident' => $record],
            $this->ticketShowPage->payload($ticket, $userId),
        ));
    }

    public function declare(Request $request, int $ticket): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();
        $this->majorIncidents->declare($ticket, $request->user()->id);

        return redirect()
            ->route('service-desk.major-incidents.war-room', $ticket)
            ->with('success', 'Major incident declared.');
    }

    public function update(Request $request, int $ticket): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();

        $this->majorIncidents->update($ticket, $request->validate([
            'coordinator_user_ids' => ['nullable', 'array'],
            'coordinator_user_ids.*' => ['integer', 'exists:users,id'],
            'war_room_notes' => ['nullable', 'string', 'max:20000'],
            'summary' => ['nullable', 'string', 'max:20000'],
            'timeline' => ['nullable', 'string', 'max:20000'],
            'lessons_learned' => ['nullable', 'string', 'max:20000'],
            'action_items' => ['nullable', 'string', 'max:20000'],
        ]));

        return back()->with('success', 'Major incident updated.');
    }

    public function resolve(Request $request, int $ticket): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();
        $this->majorIncidents->resolve($ticket, $request->user()->id);

        return back()->with('success', 'Major incident resolved. Complete the post-incident review when ready.');
    }

    public function completeReview(Request $request, int $ticket): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();

        $this->majorIncidents->completeReview($ticket, $request->validate([
            'summary' => ['required', 'string', 'max:20000'],
            'timeline' => ['nullable', 'string', 'max:20000'],
            'lessons_learned' => ['nullable', 'string', 'max:20000'],
            'action_items' => ['nullable', 'string', 'max:20000'],
        ]), $request->user()->id);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Post-incident review completed.');
    }
}
