<?php

namespace App\Domains\Tickets\Controllers;

use App\Domains\Channels\Services\ChannelService;
use App\Domains\Assets\Services\AssetService;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Integrations\Services\TicketExternalIssueService;
use App\Domains\SideConversations\Services\SideConversationService;
use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\ServiceDesk\Services\ChangeRecordService;
use App\Domains\ServiceDesk\Services\MajorIncidentService;
use App\Domains\ServiceDesk\Services\ProblemRecordService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Domains\Tickets\Services\TicketLifecycleService;
use App\Domains\Tickets\Services\TicketReadService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Tickets\Support\TicketFilters;
use App\Domains\Tickets\Services\TicketCcService;
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
        private ApprovalService $approvals,
        private ChangeRecordService $changeRecords,
        private ProblemRecordService $problemRecords,
        private MajorIncidentService $majorIncidents,
        private ServiceDeskService $serviceDesk,
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

        return Inertia::render('Tickets/Index', [
            'tickets' => $this->ticketService->listFiltered($filters, $user->id),
            'ticketViews' => $this->ticketViewService->forUser($user->id),
            'statuses' => $this->ticketService->statuses(),
            'priorities' => $this->ticketService->priorities(),
            'agents' => $this->workforceService->agentOptions(),
            'channels' => $this->channelService->all(),
            'departments' => $this->workforceService->departmentOptions(),
            'teams' => $this->workforceService->teamOptions(),
            'userTeams' => $user->teams()->orderBy('name')->get(['teams.id', 'teams.name']),
            'filters' => $filters,
            'activeViewId' => $request->integer('view_id') ?: null,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tickets/Create', [
            'statuses' => $this->ticketService->statuses(),
            'priorities' => $this->ticketService->priorities(),
            'agents' => $this->workforceService->agentOptions(),
            'departments' => $this->workforceService->departmentOptions(),
            'teams' => $this->workforceService->teamOptions(),
            'customFieldDefinitions' => $this->ticketService->fieldDefinitions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'assigned_to' => ['nullable', Rule::in($this->workforceService->assignableAgentIds())],
            'department_id' => ['nullable', 'exists:departments,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'ticket_status_id' => ['required', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['required', 'exists:ticket_priorities,id'],
            'type' => ['nullable', 'string', 'in:incident,service_request,change,problem'],
            'custom_fields' => ['nullable', 'array'],
        ], $this->peopleRules()));

        $ticket = $this->ticketService->create($data, $request->user()->id);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created.');
    }

    public function show(int $ticket, Request $request): Response
    {
        $ticketModel = $this->ticketService->show($ticket);
        $this->ticketReads->markAsRead($request->user()->id, $ticketModel->id);
        $majorIncident = $this->majorIncidents->snapshotForTicket($ticketModel->id);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticketModel,
            'sla' => $this->slaService->snapshotForTicket($ticketModel),
            'statuses' => $this->ticketService->statuses(),
            'priorities' => $this->ticketService->priorities(),
            'agents' => $this->workforceService->agentOptions(),
            'departments' => $this->workforceService->departmentOptions(),
            'teams' => $this->workforceService->teamOptions(),
            'mergeCandidates' => $this->ticketService->listFiltered([], $request->user()->id, 50)
                ->getCollection()
                ->where('id', '!=', $ticketModel->id)
                ->values(),
            'assetOptions' => $this->assetService->options(),
            'currentUserId' => $request->user()->id,
            'csat' => $this->csatService->promptForTicket($ticketModel),
            'lifecycle' => $this->lifecycleService->timeline($ticketModel->id),
            'customFieldDefinitions' => $this->ticketService->fieldDefinitions(),
            'sideConversations' => $this->sideConversationService->listForTicket($ticketModel->id),
            'timeTracking' => $this->timeTracking->snapshotForTicket($ticketModel->id),
            'externalIssues' => $this->externalIssues->listForTicket($ticketModel->id),
            'approval' => $this->approvals->snapshotForTicket($ticketModel->id),
            'canDecideApproval' => $this->canDecideApproval($ticketModel->id, $request->user()->id),
            'changeRecord' => $this->changeRecords->snapshotForTicket($ticketModel->id),
            'problemRecord' => $this->problemRecords->snapshotForTicket($ticketModel->id),
            'incidentCandidates' => $ticketModel->type === ServiceCatalogItem::TYPE_PROBLEM
                ? $this->problemRecords->incidentCandidates($ticketModel->id)->values()
                : [],
            'changeRiskOptions' => $this->changeRecords->riskOptions(),
            'majorIncident' => $majorIncident,
            'canDeclareMajorIncident' => $this->serviceDesk->isAvailable()
                && $ticketModel->type === ServiceCatalogItem::TYPE_INCIDENT
                && $majorIncident === null,
        ]);
    }

    private function canDecideApproval(int $ticketId, int $userId): bool
    {
        $snapshot = $this->approvals->snapshotForTicket($ticketId);

        if (! $snapshot || ($snapshot['status'] ?? '') !== 'pending') {
            return false;
        }

        $currentStep = collect($snapshot['steps'] ?? [])->firstWhere('step_order', $snapshot['current_step'] ?? 0);

        return (int) ($currentStep['approver']['id'] ?? 0) === $userId
            && ($currentStep['status'] ?? '') === 'pending';
    }

    public function update(Request $request, int $ticket): RedirectResponse
    {
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

        return back()->with('success', 'Ticket updated.');
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
        ]);

        $merged = $this->ticketService->merge($ticket, $data['source_ticket_id'], $request->user()->id);

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
}
