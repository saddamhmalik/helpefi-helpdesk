<?php

namespace App\Domains\Tickets\Controllers\Api;

use App\Domains\Tickets\Services\TicketCcService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use App\Domains\Tickets\Support\TicketFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private TicketViewService $ticketViewService,
        private TicketFormReferenceService $ticketReferenceData,
        private WorkforceService $workforceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = TicketFilters::normalize($request->only(TicketFilters::KEYS));

        if ($request->filled('view_id')) {
            $view = $this->ticketViewService->findAccessible(
                $request->integer('view_id'),
                $request->user()->id,
            );
            $filters = TicketFilters::normalize(array_merge($view->filters ?? [], $filters));
        }

        return response()->json(
            $this->ticketService->listFiltered($filters, $request->user()->id)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'assigned_to' => ['nullable', Rule::in($this->workforceService->assignableAgentIds())],
            'ticket_status_id' => ['required', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['required', 'exists:ticket_priorities,id'],
        ], $this->peopleRules()));

        return response()->json($this->ticketService->create($data, $request->user()->id), 201);
    }

    public function show(int $ticket): JsonResponse
    {
        return response()->json($this->ticketService->show($ticket));
    }

    public function update(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate(array_merge([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'assigned_to' => ['nullable', Rule::in($this->workforceService->assignableAgentIds())],
            'ticket_status_id' => ['sometimes', 'exists:ticket_statuses,id'],
            'ticket_priority_id' => ['sometimes', 'exists:ticket_priorities,id'],
        ], $this->peopleRules()));

        return response()->json($this->ticketService->update($ticket, $data, $request->user()->id));
    }

    public function reply(Request $request, int $ticket): JsonResponse
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

        $message = $this->ticketService->reply(
            $ticket,
            $request->user()->id,
            $data['body'] ?? '',
            $data['is_internal'] ?? false,
            $request->file('attachments', []),
        );

        return response()->json($message, 201);
    }

    public function storeAttachment(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $attachment = $this->ticketService->addAttachment($ticket, $request->user()->id, $data['file']);

        return response()->json($attachment, 201);
    }

    public function storeWatcher(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        return response()->json(
            $this->ticketService->addWatcher($ticket, $data['user_id'] ?? $request->user()->id)
        );
    }

    public function destroyWatcher(Request $request, int $ticket, int $user): JsonResponse
    {
        return response()->json($this->ticketService->removeWatcher($ticket, $user));
    }

    public function merge(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'source_ticket_id' => ['required', 'exists:tickets,id'],
            'import_conversation' => ['boolean'],
        ]);

        try {
            return response()->json(
                $this->ticketService->merge(
                    $ticket,
                    $data['source_ticket_id'],
                    $request->user()->id,
                    $request->boolean('import_conversation', true),
                )
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function split(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'from_message_id' => ['required', 'exists:ticket_messages,id'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(
            $this->ticketService->split(
                $ticket,
                $data['from_message_id'],
                $request->user()->id,
                $data['subject'] ?? null,
            ),
            201
        );
    }

    public function meta(): JsonResponse
    {
        return response()->json($this->ticketReferenceData->only([
            'statuses',
            'priorities',
            'agents',
        ]));
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
