<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Channels\Services\ChannelService;
use App\Domains\Assets\Services\AssetService;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Integrations\Services\TicketExternalIssueService;
use App\Domains\SideConversations\Services\SideConversationService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Domains\ServiceDesk\Services\TicketItsmContextService;
use App\Domains\ServiceDesk\Support\TicketTypes;
use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Domains\Tickets\Services\TicketLifecycleService;
use App\Domains\Tickets\Services\TicketReadService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Tickets\Support\TicketFilters;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Domains\Tickets\Services\TicketShowPageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private TicketViewService $ticketViewService,
        private TicketLifecycleService $lifecycleService,
        private ChannelService $channelService,
        private AssetService $assetService,
        private CsatService $csatService,
        private SlaService $slaService,
        private WorkforceService $workforceService,
        private SideConversationService $sideConversationService,
        private TimeTrackingService $timeTracking,
        private TicketExternalIssueService $externalIssues,
        private TicketReadService $ticketReads,
        private TicketItsmContextService $itsmContext,
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => [
                'nullable',
                'exists:contacts,id',
                Rule::requiredIf(fn () => blank($request->input('requester_email'))),
            ],
            'assigned_to' => ['nullable', Rule::in($this->workforceService->assignableAgentIds())],
            'department_id' => ['nullable', 'exists:departments,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'ticket_status_id' => ['required', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['required', 'exists:ticket_priorities,id'],
            'type' => ['nullable', 'string', 'in:incident,service_request,change,problem'],
            'custom_fields' => ['nullable', 'array'],
        ], $this->requesterRulesForCreate($request)));

        $ticket = $this->ticketService->create($data, $request->user()->id);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created.');
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

    public function update(Request $request, int $ticket): RedirectResponse
    {
        $autosave = $request->boolean('_autosave');

        $data = $request->validate(array_merge([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'assigned_to' => ['nullable', Rule::in($this->workforceService->assignableAgentIds())],
            'department_id' => ['nullable', 'exists:departments,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'ticket_status_id' => ['sometimes', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['sometimes', 'exists:ticket_priorities,id'],
            'custom_fields' => ['nullable', 'array'],
        ], $this->peopleRules()));

        $this->ticketService->update($ticket, $data, $request->user()->id);

        return $autosave ? back() : back()->with('success', 'Ticket updated.');
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'is_internal' => ['boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if (MessageBodySanitizer::isEmpty($data['body'] ?? '') && ! $request->hasFile('attachments')) {
            throw ValidationException::withMessages([
                'body' => 'Add a message or attach at least one file.',
            ]);
        }

        $this->ticketService->reply(
            $ticket,
            $request->user()->id,
            $data['body'] ?? '',
            $data['is_internal'] ?? false,
            $request->file('attachments', []),
        );

        return back()->with('success', 'Reply added.');
    }

    public function storeAttachment(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $this->ticketService->addAttachment($ticket, $request->user()->id, $data['file']);

        return back()->with('success', 'Attachment uploaded.');
    }

    public function storeWatcher(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $this->ticketService->addWatcher($ticket, $data['user_id'] ?? $request->user()->id);

        return back()->with('success', 'Watcher added.');
    }

    public function destroyWatcher(Request $request, int $ticket, int $user): RedirectResponse
    {
        $this->ticketService->removeWatcher($ticket, $user);

        return back()->with('success', 'Watcher removed.');
    }

    public function merge(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'source_ticket_id' => ['required', 'exists:tickets,id'],
            'import_conversation' => ['boolean'],
        ]);

        $merged = $this->ticketService->merge(
            $ticket,
            $data['source_ticket_id'],
            $request->user()->id,
            $request->boolean('import_conversation', true),
        );

        return redirect()->route('tickets.show', $merged)->with('success', 'Ticket merged.');
    }

    public function split(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'from_message_id' => ['required', 'exists:ticket_messages,id'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        $newTicket = $this->ticketService->split(
            $ticket,
            $data['from_message_id'],
            $request->user()->id,
            $data['subject'] ?? null,
        );

        return redirect()->route('tickets.show', $newTicket)->with('success', 'Ticket split.');
    }

    private function peopleRules(): array
    {
        return [
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'cc_emails' => ['nullable', 'array', 'max:'.TicketCcService::MAX_CC],
            'cc_emails.*' => ['email', 'max:255'],
        ];
    }

    private function requesterRulesForCreate(Request $request): array
    {
        return [
            'requester_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf(fn () => blank($request->input('contact_id'))),
            ],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'cc_emails' => ['nullable', 'array', 'max:'.TicketCcService::MAX_CC],
            'cc_emails.*' => ['email', 'max:255'],
        ];
    }
}
