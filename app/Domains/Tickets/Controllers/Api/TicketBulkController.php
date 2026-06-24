<?php

namespace App\Domains\Tickets\Controllers\Api;

use App\Domains\Tickets\Requests\BulkTicketActionRequest;
use App\Domains\Tickets\Services\TicketBulkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TicketBulkController extends Controller
{
    public function __construct(private TicketBulkService $bulkService)
    {
    }

    public function store(BulkTicketActionRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->bulkService->execute(
                $data['ticket_ids'],
                $data['action'],
                $data,
                $request->user()->id,
            ),
        );
    }
}
