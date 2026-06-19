<?php

namespace App\Domains\Automation\Controllers;

use App\Domains\Automation\Services\AutomationService;
use App\Domains\Contacts\Repositories\TagRepository;
use App\Domains\Integrations\Services\WebhookService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function __construct(
        private AutomationService $automationService,
        private TicketFormReferenceService $ticketReferenceData,
        private WebhookService $webhookService,
        private TagRepository $tags,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Automation', array_merge([
            'rules' => $this->automationService->all(),
            'meta' => $this->automationService->meta(),
            'webhooks' => $this->webhookService->all()->map(fn ($webhook) => [
                'id' => $webhook->id,
                'name' => $webhook->name,
            ])->values(),
            'tags' => $this->tags->all(),
        ], $this->ticketReferenceData->payload()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedRule($request);

        $this->automationService->create($data);

        return back()->with('success', 'Automation rule created.');
    }

    public function update(Request $request, int $rule): RedirectResponse
    {
        $data = $this->validatedRule($request);

        $this->automationService->update($rule, $data);

        return back()->with('success', 'Automation rule updated.');
    }

    public function destroy(int $rule): RedirectResponse
    {
        $this->automationService->delete($rule);

        return back()->with('success', 'Automation rule deleted.');
    }

    private function validatedRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required', 'string'],
            'conditions.*.operator' => ['required', 'string'],
            'conditions.*.value' => ['nullable'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.value' => ['nullable'],
            'actions.*.minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);
    }
}
