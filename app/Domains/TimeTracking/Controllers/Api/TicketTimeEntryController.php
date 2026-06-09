<?php

namespace App\Domains\TimeTracking\Controllers\Api;

use App\Domains\TimeTracking\Services\TimeTrackingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTimeEntryController extends Controller
{
    public function __construct(private TimeTrackingService $timeTracking)
    {
    }

    public function index(int $ticket): JsonResponse
    {
        return response()->json($this->timeTracking->snapshotForTicket($ticket));
    }

    public function store(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'note' => ['nullable', 'string', 'max:1000'],
            'logged_at' => ['nullable', 'date'],
        ]);

        $entry = $this->timeTracking->log(
            $ticket,
            $request->user()->id,
            (int) $data['minutes'],
            $data['note'] ?? null,
            $data['logged_at'] ?? null,
        );

        return response()->json([
            'id' => $entry->id,
            'minutes' => $entry->minutes,
            'note' => $entry->note,
            'logged_at' => $entry->logged_at?->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, int $ticket, int $entry): JsonResponse
    {
        $this->timeTracking->delete(
            $ticket,
            $entry,
            $request->user()->id,
            $request->user()->hasRole('admin'),
        );

        return response()->json(['ok' => true]);
    }
}
