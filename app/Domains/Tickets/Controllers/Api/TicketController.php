<?php

namespace App\Domains\Tickets\Controllers\Api;

use App\Domains\Tickets\Requests\Api\StoreTicketRequest;
use App\Domains\Tickets\Requests\Api\UpdateTicketRequest;
use App\Domains\Tickets\Requests\MergeTicketRequest;
use App\Domains\Tickets\Requests\ReplyTicketRequest;
use App\Domains\Tickets\Requests\SplitTicketRequest;
use App\Domains\Tickets\Requests\StoreTicketAttachmentRequest;
use App\Domains\Tickets\Requests\StoreTicketWatcherRequest;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Domains\Tickets\Services\TicketService;
use App\Domains\Tickets\Services\TicketShowPageService;
use App\Domains\Tickets\Services\TicketViewService;
use App\Domains\Tickets\Support\TicketFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private TicketViewService $ticketViewService,
        private TicketFormReferenceService $ticketReferenceData,
        private TicketShowPageService $ticketShowPage,
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

    public function store(StoreTicketRequest $request): JsonResponse
    {
        return response()->json($this->ticketService->create($request->validated(), $request->user()->id), 201);
    }

    public function panels(int $ticket): JsonResponse
    {
        return response()->json($this->ticketShowPage->lazyPanels($ticket));
    }

    public function show(int $ticket): JsonResponse
    {
        return response()->json($this->ticketService->show($ticket));
    }

    public function update(UpdateTicketRequest $request, int $ticket): JsonResponse
    {
        return response()->json($this->ticketService->update($ticket, $request->validated(), $request->user()->id));
    }

    public function reply(ReplyTicketRequest $request, int $ticket): JsonResponse
    {
        $data = $request->validated();

        $message = $this->ticketService->reply(
            $ticket,
            $request->user()->id,
            $data['body'] ?? '',
            $data['is_internal'] ?? false,
            $request->file('attachments', []),
        );

        return response()->json($message, 201);
    }

    public function storeAttachment(StoreTicketAttachmentRequest $request, int $ticket): JsonResponse
    {
        $attachment = $this->ticketService->addAttachment($ticket, $request->user()->id, $request->file('file'));

        return response()->json($attachment, 201);
    }

    public function storeWatcher(StoreTicketWatcherRequest $request, int $ticket): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->ticketService->addWatcher($ticket, $data['user_id'] ?? $request->user()->id)
        );
    }

    public function destroyWatcher(Request $request, int $ticket, int $user): JsonResponse
    {
        return response()->json($this->ticketService->removeWatcher($ticket, $user));
    }

    public function merge(MergeTicketRequest $request, int $ticket): JsonResponse
    {
        $data = $request->validated();

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

    public function split(SplitTicketRequest $request, int $ticket): JsonResponse
    {
        $data = $request->validated();

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
}
