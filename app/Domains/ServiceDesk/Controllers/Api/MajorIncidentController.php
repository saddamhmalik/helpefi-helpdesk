<?php

namespace App\Domains\ServiceDesk\Controllers\Api;

use App\Domains\ServiceDesk\Services\MajorIncidentService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MajorIncidentController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private MajorIncidentService $majorIncidents,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->majorIncidents->index());
    }

    public function forTicket(int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->majorIncidents->snapshotForTicket($ticket));
    }

    public function declare(Request $request, int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json(
            $this->majorIncidents->declare($ticket, $request->user()->id),
            201,
        );
    }

    public function update(Request $request, int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->majorIncidents->update($ticket, $request->validate([
            'coordinator_user_ids' => ['nullable', 'array'],
            'coordinator_user_ids.*' => ['integer', 'exists:users,id'],
            'war_room_notes' => ['nullable', 'string', 'max:20000'],
            'summary' => ['nullable', 'string', 'max:20000'],
            'timeline' => ['nullable', 'string', 'max:20000'],
            'lessons_learned' => ['nullable', 'string', 'max:20000'],
            'action_items' => ['nullable', 'string', 'max:20000'],
        ])));
    }

    public function resolve(Request $request, int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->majorIncidents->resolve($ticket, $request->user()->id));
    }

    public function completeReview(Request $request, int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->majorIncidents->completeReview($ticket, $request->validate([
            'summary' => ['required', 'string', 'max:20000'],
            'timeline' => ['nullable', 'string', 'max:20000'],
            'lessons_learned' => ['nullable', 'string', 'max:20000'],
            'action_items' => ['nullable', 'string', 'max:20000'],
        ]), $request->user()->id));
    }
}
