<?php

namespace App\Domains\Sla\Controllers\Api;

use App\Domains\Sla\Services\SlaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaPolicyController extends Controller
{
    public function __construct(private SlaService $slaService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->slaService->policies());
    }

    public function show(int $policy): JsonResponse
    {
        return response()->json($this->slaService->showPolicy($policy));
    }

    public function updateTarget(Request $request, int $target): JsonResponse
    {
        $data = $request->validate([
            'first_response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($this->slaService->updateTarget($target, $data));
    }

    public function ticketTimer(int $ticket): JsonResponse
    {
        $timer = $this->slaService->timerForTicket($ticket);

        if (! $timer) {
            return response()->json(['message' => 'No SLA timer for this ticket.'], 404);
        }

        return response()->json($timer);
    }
}
