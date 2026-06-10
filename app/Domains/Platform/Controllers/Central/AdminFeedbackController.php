<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\PlatformFeedbackService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFeedbackController extends Controller
{
    public function __construct(private PlatformFeedbackService $feedback)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'string', 'max:255'],
        ]);

        return Inertia::render('Central/Admin/Feedback/Index', [
            'submissions' => $this->feedback->list($filters),
            'filters' => $filters,
            'types' => config('platform_feedback.types', []),
            'statuses' => config('platform_feedback.statuses', []),
            'summary' => $this->feedback->summary(),
        ]);
    }

    public function show(int $feedback): Response
    {
        return Inertia::render('Central/Admin/Feedback/Show', [
            'submission' => $this->feedback->find($feedback),
            'types' => config('platform_feedback.types', []),
            'statuses' => config('platform_feedback.statuses', []),
        ]);
    }

    public function updateStatus(Request $request, int $feedback): RedirectResponse
    {
        $data = $request->validate($this->feedback->statusRules());

        $this->feedback->updateStatus($feedback, $data['status']);

        return back()->with('success', 'Submission status updated.');
    }
}
