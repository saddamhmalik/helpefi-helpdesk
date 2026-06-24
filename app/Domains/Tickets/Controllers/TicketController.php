<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Domains\ServiceDesk\Support\TicketTypes;
use App\Domains\Tickets\Requests\MergeTicketRequest;
use App\Domains\Tickets\Requests\ReplyTicketRequest;
use App\Domains\Tickets\Requests\SplitTicketRequest;
use App\Domains\Tickets\Requests\StoreTicketAttachmentRequest;
use App\Domains\Tickets\Requests\StoreTicketRequest;
use App\Domains\Tickets\Requests\StoreTicketWatcherRequest;
use App\Domains\Tickets\Requests\UpdateTicketRequest;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Tickets\Support\TicketFilters;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Domains\Tickets\Services\TicketShowPageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private TicketViewService $ticketViewService,
        private ServiceDeskService $serviceDesk,
        private TicketShowPageService $ticketShowPage,
        private TicketFormReferenceService $ticketReferenceData,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = TicketFilters::normalize($request->only(TicketFilters::KEYS));

        if ($request->filled('view_id')) {
            $view = $this->ticketViewService->findAccessible(
                $request->integer('view_id'),
                $request->user()->id,
            );
            $filters = TicketFilters::normalize(array_merge($view->filters ?? [], $filters));
        }

        $user = $request->user();

        return Inertia::render('Tickets/Index', array_merge([
            'tickets' => $this->ticketService->listFiltered($filters, $user->id),
            'ticketViews' => $this->ticketViewService->forUser($user->id),
            'userTeams' => $user->teams()->orderBy('name')->get(['teams.id', 'teams.name']),
            'filters' => $filters,
            'activeViewId' => $request->integer('view_id') ?: null,
        ], $this->ticketReferenceData->payload()));
    }

    public function create(Request $request): Response
    {
        $requestedType = (string) $request->query('type', '');
        $defaultType = TicketTypes::isValid($requestedType) ? $requestedType : null;

        return Inertia::render('Tickets/Create', array_merge(
            $this->ticketReferenceData->payload(),
            [
                'ticketTypes' => $this->serviceDesk->isAvailable() ? $this->serviceDesk->ticketTypes() : [],
                'defaultType' => $defaultType,
            ],
        ));
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $this->ticketService->create($request->validated(), $request->user()->id);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created.');
    }

    public function panels(int $ticket): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->ticketShowPage->lazyPanels($ticket));
    }

    public function show(int $ticket, Request $request): Response
    {
        return Inertia::render('Tickets/Show', $this->ticketShowPage->payload($ticket, $request->user()->id));
    }

    public function mergeCandidates(int $ticket, Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'mergeCandidates' => $this->ticketShowPage->mergeCandidates($request->user()->id, $ticket),
        ]);
    }

    public function update(UpdateTicketRequest $request, int $ticket): RedirectResponse
    {
        $autosave = $request->boolean('_autosave');

        $this->ticketService->update($ticket, $request->validated(), $request->user()->id);

        return $autosave ? back() : back()->with('success', 'Ticket updated.');
    }

    public function reply(ReplyTicketRequest $request, int $ticket): RedirectResponse
    {
        $data = $request->validated();

        $this->ticketService->reply(
            $ticket,
            $request->user()->id,
            $data['body'] ?? '',
            $data['is_internal'] ?? false,
            $request->file('attachments', []),
        );

        return back()->with('success', 'Reply added.');
    }

    public function storeAttachment(StoreTicketAttachmentRequest $request, int $ticket): RedirectResponse
    {
        $this->ticketService->addAttachment($ticket, $request->user()->id, $request->file('file'));

        return back()->with('success', 'Attachment uploaded.');
    }

    public function storeWatcher(StoreTicketWatcherRequest $request, int $ticket): RedirectResponse
    {
        $data = $request->validated();

        $this->ticketService->addWatcher($ticket, $data['user_id'] ?? $request->user()->id);

        return back()->with('success', 'Watcher added.');
    }

    public function destroyWatcher(Request $request, int $ticket, int $user): RedirectResponse
    {
        $this->ticketService->removeWatcher($ticket, $user);

        return back()->with('success', 'Watcher removed.');
    }

    public function merge(MergeTicketRequest $request, int $ticket): RedirectResponse
    {
        $data = $request->validated();

        $merged = $this->ticketService->merge(
            $ticket,
            $data['source_ticket_id'],
            $request->user()->id,
            $request->boolean('import_conversation', true),
        );

        return redirect()->route('tickets.show', $merged)->with('success', 'Ticket merged.');
    }

    public function split(SplitTicketRequest $request, int $ticket): RedirectResponse
    {
        $data = $request->validated();

        $newTicket = $this->ticketService->split(
            $ticket,
            $data['from_message_id'],
            $request->user()->id,
            $data['subject'] ?? null,
        );

        return redirect()->route('tickets.show', $newTicket)->with('success', 'Ticket split.');
    }
}
