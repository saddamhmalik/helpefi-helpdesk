<?php

namespace App\Domains\Workspace\Controllers\Api;

use App\Domains\Tickets\Services\TicketService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Domains\Workspace\Services\WorkspaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function __construct(
        private WorkspaceService $workspaceService,
        private TicketService $ticketService,
        private WorkforceService $workforceService,
    ) {
    }

    public function queue(Request $request): JsonResponse
    {
        $filters = $request->only(['status_id', 'priority_id', 'assigned_to', 'search', 'watching']);

        return response()->json(
            $this->workspaceService->queue($filters, $request->user()->id)
        );
    }

    public function show(int $ticket): JsonResponse
    {
        return response()->json($this->workspaceService->ticket($ticket));
    }

    public function pollTicket(Request $request, int $ticket): JsonResponse
    {
        return response()->json(
            $this->workspaceService->pollTicket($ticket, $request->query('since'))
        );
    }

    public function pollQueue(Request $request): JsonResponse
    {
        return response()->json(
            $this->workspaceService->pollQueue($request->query('since'))
        );
    }

    public function saveDraft(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'is_internal' => ['boolean'],
        ]);

        return response()->json(
            $this->workspaceService->saveDraft(
                $request->user()->id,
                $ticket,
                $data['body'] ?? null,
                $data['is_internal'] ?? false,
            )
        );
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

    public function meta(): JsonResponse
    {
        return response()->json([
            'statuses' => $this->ticketService->statuses(),
            'priorities' => $this->ticketService->priorities(),
            'agents' => $this->workforceService->agentOptions(),
        ]);
    }
}
