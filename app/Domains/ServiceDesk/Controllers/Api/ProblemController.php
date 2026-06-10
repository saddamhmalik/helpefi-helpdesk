<?php

namespace App\Domains\ServiceDesk\Controllers\Api;

use App\Domains\ServiceDesk\Services\ProblemRecordService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProblemController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private ProblemRecordService $problems,
    ) {
    }

    public function forTicket(int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->problems->snapshotForTicket($ticket));
    }

    public function update(Request $request, int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->problems->update($ticket, $request->validate([
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'workaround' => ['nullable', 'string', 'max:10000'],
            'is_known_error' => ['boolean'],
        ])));
    }

    public function linkIncident(Request $request, int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        $data = $request->validate([
            'incident_ticket_id' => ['required', 'integer', 'exists:tickets,id'],
        ]);

        return response()->json(
            $this->problems->linkIncident($ticket, (int) $data['incident_ticket_id'], $request->user()->id),
        );
    }

    public function unlinkIncident(int $ticket, int $incident): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->problems->unlinkIncident($ticket, $incident));
    }

    public function incidentCandidates(int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json(
            $this->problems->incidentCandidates($ticket)->values(),
        );
    }
}
