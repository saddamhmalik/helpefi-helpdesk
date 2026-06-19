<?php

namespace App\Domains\Sla\Controllers;

use App\Domains\Sla\Services\BusinessHoursService;
use App\Domains\Sla\Services\SlaEscalationService;
use App\Domains\Sla\Services\SlaService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlaPolicyController extends Controller
{
    public function __construct(
        private SlaService $slaService,
        private SlaEscalationService $escalations,
        private BusinessHoursService $businessHours,
        private TicketFormReferenceService $ticketReferenceData,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Sla', array_merge([
            'policies' => $this->slaService->policies(),
            'breachedCount' => $this->slaService->breachedCount(),
            'escalationRules' => $this->escalations->rulesForPolicies(),
            'escalationMeta' => $this->escalations->meta(),
            'slaMeta' => $this->slaService->meta(),
            'businessHours' => $this->businessHours->optionalSnapshot(),
            'timezoneOptions' => $this->businessHours->timezoneOptions(),
            'weekdays' => $this->businessHours->weekdayMeta(),
        ], $this->ticketReferenceData->only(['teams'])));
    }

    public function updateBusinessHours(Request $request, int $businessHours): RedirectResponse
    {
        $data = $request->validate($this->businessHours->updateValidationRules());

        $this->businessHours->update($businessHours, $data);

        return back()->with('success', 'Business hours updated.');
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'customer_tier' => ['nullable', 'string', 'in:'.implode(',', array_column(config('customer_tiers', []), 'value'))],
        ]);

        $this->slaService->createScopedPolicy(
            $data['name'],
            filled($data['team_id'] ?? null) ? (int) $data['team_id'] : null,
            $data['customer_tier'] ?? null,
        );

        return back()->with('success', 'SLA policy created.');
    }

    public function destroyPolicy(int $policy): RedirectResponse
    {
        $this->slaService->deletePolicy($policy);

        return back()->with('success', 'SLA policy deleted.');
    }

    public function updateTarget(Request $request, int $target): RedirectResponse
    {
        $data = $request->validate([
            'first_response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
        ]);

        $this->slaService->updateTarget($target, $data);

        return back()->with('success', 'SLA target updated.');
    }

    public function storeEscalation(Request $request): RedirectResponse
    {
        $data = $request->validate($this->escalations->storeValidationRules());

        $this->escalations->saveRule($data);

        return back()->with('success', 'Escalation rule saved.');
    }

    public function destroyEscalation(int $rule): RedirectResponse
    {
        $this->escalations->deleteRule($rule);

        return back()->with('success', 'Escalation rule deleted.');
    }
}
