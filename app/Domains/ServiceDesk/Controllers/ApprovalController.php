<?php

namespace App\Domains\ServiceDesk\Controllers;

use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Domains\ServiceDesk\Services\ServiceDeskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private ServiceDeskService $serviceDesk,
        private ApprovalService $approvals,
    ) {
    }

    public function index(Request $request): Response
    {
        if (! $this->serviceDesk->isAvailable()) {
            return Inertia::render('ServiceDesk/Upgrade', $this->serviceDesk->upgradeContext());
        }

        $user = $request->user();
        $mine = $request->boolean('mine');

        return Inertia::render('ServiceDesk/Approvals/Index', [
            'approvals' => $this->approvals->list([
                'status' => $request->query('status', 'pending'),
                'mine' => $mine,
                'user_id' => $user->id,
            ], 20),
            'filters' => [
                'status' => $request->query('status', 'pending'),
                'mine' => $mine,
            ],
            'pendingMine' => $this->approvals->pendingCountForUser($user->id),
            'currentUserId' => $user->id,
        ]);
    }

    public function settings(): Response
    {
        $this->serviceDesk->assertAvailable();

        return Inertia::render('Settings/ServiceDeskApprovals', $this->approvals->settingsSnapshot());
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();
        $this->approvals->updateSettings($request->validate([
            'change_requires_approval' => ['boolean'],
            'change_approver_user_ids' => ['nullable', 'array'],
            'change_approver_user_ids.*' => ['integer', 'exists:users,id'],
        ]));

        return back()->with('success', 'Change approval settings updated.');
    }

    public function approve(Request $request, int $approval): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->approvals->approve($approval, $request->user()->id, $data['note'] ?? null);

        return back()->with('success', 'Approval recorded.');
    }

    public function reject(Request $request, int $approval): RedirectResponse
    {
        $this->serviceDesk->assertAvailable();
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->approvals->reject($approval, $request->user()->id, $data['note'] ?? null);

        return back()->with('success', 'Request rejected.');
    }
}
