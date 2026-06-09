<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\InvitationService;
use App\Domains\Auth\Services\MemberProfileService;
use App\Domains\Auth\Services\MemberService;
use App\Domains\Auth\Services\RoleService;
use App\Domains\Workforce\Services\SkillService;
use App\Domains\Workforce\Services\WorkforceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        private MemberService $memberService,
        private InvitationService $invitationService,
        private RoleService $roleService,
        private WorkforceService $workforceService,
        private MemberProfileService $memberProfileService,
        private SkillService $skillService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Settings/Members', [
            'employees' => $this->memberService->listEmployees(),
            'pendingInvitations' => $this->invitationService->pending(),
            'roles' => $this->roleService->assignableRoles(),
            'customFieldDefinitions' => $this->memberService->fieldDefinitions(),
            'departments' => $this->workforceService->departmentOptions(),
            'teams' => $this->workforceService->teamOptions(),
        ]);
    }

    public function show(int $member): Response
    {
        return Inertia::render('Settings/Members/Show', array_merge(
            $this->memberProfileService->show($member),
            [
                'allSkills' => $this->skillService->options(),
                'teams' => $this->workforceService->teamOptions(),
            ],
        ));
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in($this->roleService->assignableRoles())],
            'team_id' => ['nullable', 'exists:teams,id'],
        ]);

        $invitation = $this->invitationService->invite(
            $request->user()->id,
            $data['email'],
            $data['role'],
            $data['team_id'] ?? null,
        );

        return back()->with([
            'success' => 'Invitation sent.',
            'invite_url' => $this->invitationService->acceptUrl($invitation),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in($this->roleService->assignableRoles())],
            'team_id' => ['nullable', 'exists:teams,id'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $this->memberService->create(
            $data['name'],
            $data['email'],
            $data['role'],
            $request->user(),
            $data['custom_fields'] ?? [],
            $data['team_id'] ?? null,
        );

        return back()->with('success', 'Team member added. They will receive an email to set their password.');
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in($this->roleService->assignableRoles())],
        ]);

        $this->memberService->updateRole($member, $data['role'], $request->user());

        return back()->with('success', 'Member role updated.');
    }

    public function updateCustomFields(Request $request, int $member): RedirectResponse
    {
        $data = $request->validate([
            'custom_fields' => ['nullable', 'array'],
        ]);

        $this->memberService->updateCustomFields($member, $data['custom_fields'] ?? [], $request->user());

        return back()->with('success', 'Member fields updated.');
    }

    public function updateSkills(Request $request, int $member): RedirectResponse
    {
        $data = $request->validate([
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $this->skillService->syncForMember($member, $data['skill_ids'] ?? []);

        return back()->with('success', 'Member skills updated.');
    }

    public function updateTeams(Request $request, int $member): RedirectResponse
    {
        $data = $request->validate([
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        $this->memberService->updateTeams($member, $data['team_ids'] ?? [], $request->user());

        return back()->with('success', 'Team membership updated.');
    }

    public function destroy(Request $request, int $member): RedirectResponse
    {
        $this->memberService->remove($member, $request->user());

        return back()->with('success', 'Member removed.');
    }
}
