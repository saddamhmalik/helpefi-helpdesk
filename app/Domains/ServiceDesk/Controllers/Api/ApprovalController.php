<?php

namespace App\Domains\ServiceDesk\Controllers\Api;

use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private ApprovalService $approvals,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->approvals->list([
            'status' => $request->query('status', 'pending'),
            'mine' => $request->boolean('mine'),
            'user_id' => $request->user()->id,
        ]));
    }

    public function settings(): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->approvals->settingsSnapshot());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->approvals->updateSettings($request->validate([
            'change_requires_approval' => ['boolean'],
            'change_approver_user_ids' => ['nullable', 'array'],
            'change_approver_user_ids.*' => ['integer', 'exists:users,id'],
        ])));
    }

    public function approve(Request $request, int $approval): JsonResponse
    {
        $this->serviceDesk->assertAvailable();
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        return response()->json(
            $this->approvals->approve($approval, $request->user()->id, $data['note'] ?? null),
        );
    }

    public function reject(Request $request, int $approval): JsonResponse
    {
        $this->serviceDesk->assertAvailable();
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        return response()->json(
            $this->approvals->reject($approval, $request->user()->id, $data['note'] ?? null),
        );
    }

    public function forTicket(int $ticket): JsonResponse
    {
        $this->serviceDesk->assertAvailable();

        return response()->json($this->approvals->snapshotForTicket($ticket));
    }
}
