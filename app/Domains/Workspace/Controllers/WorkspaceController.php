<?php

namespace App\Domains\Workspace\Controllers;

use App\Domains\Assets\Services\AssetService;
use App\Domains\Csat\Services\CsatService;
use App\Domains\Integrations\Services\TicketExternalIssueService;
use App\Domains\SideConversations\Services\SideConversationService;
use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Services\TicketLifecycleService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Domains\Tickets\Services\TicketSnoozeService;
use App\Domains\Workspace\Services\WorkspaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(
        private WorkspaceService $workspaceService,
        private TicketService $ticketService,
        private TicketViewService $ticketViewService,
        private WorkforceService $workforceService,
        private SlaService $slaService,
        private TicketLifecycleService $lifecycleService,
        private AssetService $assetService,
        private CsatService $csatService,
        private SideConversationService $sideConversationService,
        private TimeTrackingService $timeTracking,
        private TicketExternalIssueService $externalIssues,
        private TicketSnoozeService $snoozeService,
    ) {
    }

    public function index(Request $request, ?int $ticket = null): Response
    {
        $filters = $request->only(['status_id', 'priority_id', 'assigned_to', 'search', 'watching']);

        if ($request->filled('view_id')) {
            $view = $this->ticketViewService->findForUser(
                $request->integer('view_id'),
                $request->user()->id,
            );
            $filters = array_merge($view->filters ?? [], array_filter($filters));
        }

        $selectedTicket = null;
        $draft = null;

        $userId = $request->user()->id;

        if ($ticket) {
            $selectedTicket = $this->workspaceService->ticket($ticket);
            $draft = $this->workspaceService->draft($userId, $ticket);
            $this->workspaceService->markTicketRead($userId, $ticket);
        }

        return Inertia::render('Workspace/Index', [
            'queue' => $this->workspaceService->queue($filters, $userId),
            'selectedTicket' => $selectedTicket,
            'draft' => $draft,
            'ticketViews' => $this->ticketViewService->forUser($userId),
            'statuses' => $this->ticketService->statuses(),
            'priorities' => $this->ticketService->priorities(),
            'agents' => $this->workforceService->agentOptions(),
            'departments' => $this->workforceService->departmentOptions(),
            'teams' => $this->workforceService->teamOptions(),
            'filters' => $filters,
            'activeViewId' => $request->integer('view_id') ?: null,
            'currentUserId' => $userId,
            'sla' => $selectedTicket ? $this->slaService->snapshotForTicket($selectedTicket) : null,
            'lifecycle' => $selectedTicket ? $this->lifecycleService->timeline($selectedTicket->id) : [],
            'mergeCandidates' => $selectedTicket
                ? $this->ticketService->listFiltered([], $userId, 50)
                    ->getCollection()
                    ->where('id', '!=', $selectedTicket->id)
                    ->values()
                : [],
            'assetOptions' => $this->assetService->options(),
            'csat' => $selectedTicket ? $this->csatService->promptForTicket($selectedTicket) : null,
            'customFieldDefinitions' => $this->ticketService->fieldDefinitions(),
            'sideConversations' => $selectedTicket
                ? $this->sideConversationService->listForTicket($selectedTicket->id)
                : [],
            'timeTracking' => $selectedTicket
                ? $this->timeTracking->snapshotForTicket($selectedTicket->id)
                : null,
            'externalIssues' => $selectedTicket
                ? $this->externalIssues->refreshForTicket($selectedTicket->id)
                : [],
            'issueProviders' => $this->externalIssues->configuredIssueProviders(),
        ]);
    }

    public function pollTicket(Request $request, int $ticket): JsonResponse
    {
        $user = $request->user();

        return response()->json(
            $this->workspaceService->pollTicket(
                $ticket,
                $request->query('since'),
                $user->id,
                $request->integer('pulse') ?: null,
            )
        );
    }

    public function presence(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'composing' => ['boolean'],
        ]);

        $user = $request->user();
        $this->workspaceService->heartbeat(
            $ticket,
            $user->id,
            $user->name,
            $data['composing'] ?? false,
        );

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, int $ticket): JsonResponse
    {
        $this->workspaceService->leaveTicket($ticket, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function pollQueue(Request $request): JsonResponse
    {
        $ticketIds = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $request->query('ticket_ids', '')),
        )));

        return response()->json(
            $this->workspaceService->pollQueue(
                $request->query('since'),
                $request->user()->id,
                $ticketIds,
            )
        );
    }

    public function markRead(Request $request, int $ticket): JsonResponse
    {
        $this->workspaceService->markTicketRead(
            $request->user()->id,
            $ticket,
            $request->integer('message_id') ?: null,
        );

        return response()->json(['unread_count' => 0]);
    }

    public function saveDraft(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'is_internal' => ['boolean'],
        ]);

        $draft = $this->workspaceService->saveDraft(
            $request->user()->id,
            $ticket,
            $data['body'] ?? null,
            $data['is_internal'] ?? false,
        );

        return response()->json($draft);
    }

    public function reply(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'is_internal' => ['boolean'],
        ]);

        $message = $this->workspaceService->reply(
            $ticket,
            $request->user()->id,
            $data['body'],
            $data['is_internal'] ?? false,
        );

        return response()->json([
            'message' => $message,
            'ticket' => $this->workspaceService->ticket($ticket),
        ]);
    }

    public function quickUpdate(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'ticket_status_id' => ['sometimes', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['sometimes', 'exists:ticket_priorities,id'],
            'assigned_to' => ['nullable', Rule::in($this->workforceService->assignableAgentIds())],
        ]);

        return response()->json(
            $this->workspaceService->quickUpdate($ticket, $data)
        );
    }

    public function snooze(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['required_without:until', 'integer', 'min:15', 'max:10080'],
            'until' => ['required_without:minutes', 'date', 'after:now'],
        ]);

        $until = isset($data['until'])
            ? Carbon::parse($data['until'])
            : now()->addMinutes((int) $data['minutes']);

        return response()->json(
            $this->snoozeService->snooze($ticket, $until, $request->user()->id)
        );
    }

    public function unsnooze(Request $request, int $ticket): JsonResponse
    {
        return response()->json(
            $this->snoozeService->unsnooze($ticket, $request->user()->id)
        );
    }
}
