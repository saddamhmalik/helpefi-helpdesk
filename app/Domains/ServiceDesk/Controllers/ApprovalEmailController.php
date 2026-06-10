<?php

namespace App\Domains\ServiceDesk\Controllers;

use App\Domains\ServiceDesk\Models\ApprovalRequestStep;
use App\Domains\ServiceDesk\Repositories\ApprovalRequestRepository;
use App\Domains\ServiceDesk\Services\ApprovalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalEmailController extends Controller
{
    public function __construct(
        private ApprovalService $approvals,
        private ApprovalRequestRepository $requests,
    ) {
    }

    public function review(Request $request, int $approval): Response|RedirectResponse
    {
        $request->validate([
            'step' => ['required', 'integer'],
            'approver' => ['required', 'integer'],
        ]);

        $approvalRequest = $this->requests->find($approval);
        $step = $approvalRequest->steps->firstWhere('id', (int) $request->integer('step'));

        if (! $step || (int) $step->approver_user_id !== (int) $request->integer('approver')) {
            abort(403);
        }

        if (! $approvalRequest->isPending() || (int) $step->step_order !== (int) $approvalRequest->current_step) {
            return redirect()->route('service-desk.approvals.index')
                ->with('success', 'This approval request has already been decided.');
        }

        if ($request->user()) {
            if ($this->approvals->canUserDecide($approvalRequest, $request->user()->id)) {
                return Inertia::render('ServiceDesk/Approvals/Review', [
                    'approval' => $approvalRequest,
                    'step' => $step,
                    'canDecide' => true,
                ]);
            }
        }

        return Inertia::render('ServiceDesk/Approvals/Review', [
            'approval' => $approvalRequest,
            'step' => $step,
            'canDecide' => false,
            'loginUrl' => route('login'),
        ]);
    }

    public function approveSigned(Request $request, int $approval): RedirectResponse
    {
        $this->assertSignedStep($request, $approval);

        $this->approvals->approve(
            $approval,
            (int) $request->integer('approver'),
            $request->string('note')->toString() ?: null,
        );

        return redirect()->route('service-desk.approvals.index', ['mine' => 1])
            ->with('success', 'Request approved.');
    }

    public function rejectSigned(Request $request, int $approval): RedirectResponse
    {
        $this->assertSignedStep($request, $approval);

        $this->approvals->reject(
            $approval,
            (int) $request->integer('approver'),
            $request->string('note')->toString() ?: null,
        );

        return redirect()->route('service-desk.approvals.index', ['mine' => 1])
            ->with('success', 'Request rejected.');
    }

    private function assertSignedStep(Request $request, int $approval): void
    {
        $request->validate([
            'step' => ['required', 'integer'],
            'approver' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $approvalRequest = $this->requests->find($approval);
        $step = $approvalRequest->steps->firstWhere('id', (int) $request->integer('step'));

        if (! $step instanceof ApprovalRequestStep
            || (int) $step->approver_user_id !== (int) $request->integer('approver')
            || ! $this->approvals->canUserDecide($approvalRequest, (int) $request->integer('approver'))) {
            abort(403);
        }
    }
}
