<?php

namespace App\Domains\Platform\Controllers\Tenant;

use App\Domains\Platform\Services\PlatformFeedbackService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformFeedbackController extends Controller
{
    public function __construct(private PlatformFeedbackService $feedback)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Settings/PlatformFeedback', [
            'types' => config('platform_feedback.types', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->feedback->submissionRules());

        $this->feedback->submit($request->user(), $data, $request);

        return back()->with('success', __('messages.feedback_submitted'));
    }
}
