<?php

namespace App\Domains\ServiceDesk\Controllers\Api;

use App\Domains\ServiceDesk\Services\ChangeRecordService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangeController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private ChangeRecordService $changes,
    ) {
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->changes->calendar(
            $request->query('from'),
            $request->query('to'),
        ));
    }

    public function forTicket(int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->changes->snapshotForTicket($ticket));
    }

    public function update(Request $request, int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->changes->update($ticket, $request->validate([
            'risk' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'impact' => ['nullable', 'string', 'max:5000'],
            'rollback_plan' => ['nullable', 'string', 'max:5000'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
            'cab_user_ids' => ['nullable', 'array'],
            'cab_user_ids.*' => ['integer', 'exists:users,id'],
            'cab_notes' => ['nullable', 'string', 'max:5000'],
            'implementation_notes' => ['nullable', 'string', 'max:5000'],
        ])));
    }
}
