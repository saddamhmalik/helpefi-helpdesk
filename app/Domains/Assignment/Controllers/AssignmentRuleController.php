<?php

namespace App\Domains\Assignment\Controllers;

use App\Domains\Assignment\Services\AssignmentService;
use App\Domains\Tickets\Services\TicketFormReferenceService;
use App\Domains\Workforce\Services\SkillService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentRuleController extends Controller
{
    public function __construct(
        private AssignmentService $assignment,
        private TicketFormReferenceService $ticketReferenceData,
        private SkillService $skills,
    ) {
    }

    public function index(): Response
    {
        $reference = $this->ticketReferenceData->only([
            'departments',
            'teams',
            'channels',
            'priorities',
        ]);

        return Inertia::render('Settings/Assignment', array_merge([
            'rules' => $this->assignment->all(),
            'meta' => $this->assignment->meta(),
            'skills' => $this->skills->options(),
        ], $reference));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assignment->create($this->validated($request));

        return back()->with('success', 'Assignment rule created.');
    }

    public function update(Request $request, int $rule): RedirectResponse
    {
        $this->assignment->update($rule, $this->validated($request));

        return back()->with('success', 'Assignment rule updated.');
    }

    public function destroy(int $rule): RedirectResponse
    {
        $this->assignment->delete($rule);

        return back()->with('success', 'Assignment rule deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'strategy' => ['required', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => ['integer', 'exists:channels,id'],
            'ticket_priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ]);
    }
}
