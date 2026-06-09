<?php

namespace App\Domains\Workforce\Controllers;

use App\Domains\Workforce\Services\WorkforceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkforceController extends Controller
{
    public function __construct(private WorkforceService $workforce)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Workforce', [
            'departments' => $this->workforce->catalog(),
            'agents' => $this->workforce->agentOptions(),
            'meta' => $this->workforce->meta(),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $this->workforce->createDepartment($data);

        return back()->with('success', 'Department created.');
    }

    public function updateDepartment(Request $request, int $department): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $this->workforce->updateDepartment($department, $data);

        return back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(int $department): RedirectResponse
    {
        $this->workforce->deleteDepartment($department);

        return back()->with('success', 'Department deleted.');
    }

    public function storeTeam(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lead_user_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'members' => ['nullable', 'array'],
            'members.*.user_id' => ['required', 'exists:users,id'],
            'members.*.org_role' => ['required', 'in:member,team_lead'],
        ]);

        $this->workforce->createTeam($data);

        return back()->with('success', 'Team created.');
    }

    public function updateTeam(Request $request, int $team): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lead_user_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'members' => ['nullable', 'array'],
            'members.*.user_id' => ['required', 'exists:users,id'],
            'members.*.org_role' => ['required', 'in:member,team_lead'],
        ]);

        $this->workforce->updateTeam($team, $data);

        return back()->with('success', 'Team updated.');
    }

    public function destroyTeam(int $team): RedirectResponse
    {
        $this->workforce->deleteTeam($team);

        return back()->with('success', 'Team deleted.');
    }
}
