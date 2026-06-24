<?php

namespace App\Domains\Platform\Controllers\Central;

use App\Domains\Platform\Services\MarketingLeadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketingLeadController extends Controller
{
    public function __construct(private MarketingLeadService $leads) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate($this->leads->filterRules());

        $filters = array_filter([
            'q' => isset($validated['q']) ? trim($validated['q']) : null,
            'source' => $validated['source'] ?? null,
            'intent' => $validated['intent'] ?? null,
            'status' => $validated['status'] ?? null,
            'consent' => $validated['consent'] ?? null,
        ]);

        $perPage = $validated['per_page'] ?? 20;

        return Inertia::render('Central/Admin/Leads/Index', [
            'leads' => $this->leads->list($perPage, $filters),
            'stats' => $this->leads->stats(),
            'filters' => array_merge([
                'q' => '',
                'source' => '',
                'intent' => '',
                'status' => '',
                'consent' => '',
            ], $filters),
            'sources' => config('platform_marketing_leads.sources', []),
            'intents' => config('platform_marketing_leads.intents', []),
            'statuses' => config('platform_marketing_leads.statuses', []),
        ]);
    }

    public function show(int $lead): Response
    {
        return Inertia::render('Central/Admin/Leads/Show', [
            'lead' => $this->leads->find($lead),
            'sources' => config('platform_marketing_leads.sources', []),
            'intents' => config('platform_marketing_leads.intents', []),
            'statuses' => config('platform_marketing_leads.statuses', []),
        ]);
    }

    public function updateStatus(Request $request, int $lead): RedirectResponse
    {
        $data = $request->validate($this->leads->statusRules());

        $this->leads->updateStatus($lead, $data['status']);

        return back()->with('success', 'Lead status updated.');
    }

    public function updateNotes(Request $request, int $lead): RedirectResponse
    {
        $data = $request->validate($this->leads->notesRules());

        $this->leads->updateNotes($lead, $data['notes'] ?? null);

        return back()->with('success', 'Lead notes saved.');
    }
}
