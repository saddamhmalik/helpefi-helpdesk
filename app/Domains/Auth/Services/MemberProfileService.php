<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Repositories\MemberProfileRepository;
use App\Domains\Performance\Services\PerformanceService;
use App\Domains\Settings\Services\HelpdeskSettingService;
use App\Models\User;

use App\Support\AvatarSupport;

class MemberProfileService
{
    public function __construct(
        private MemberProfileRepository $profiles,
        private PerformanceService $performance,
        private HelpdeskSettingService $helpdeskSettings,
    ) {
    }

    public function show(int $memberId): array
    {
        $member = $this->profiles->findEmployee($memberId);
        $teamIds = $member->teams->pluck('id')->all();
        $departmentIds = $member->teams->pluck('department_id')->filter()->unique()->values()->all();

        return [
            'member' => $this->serializeMember($member),
            'memberships' => $this->serializeMemberships($member),
            'departments' => $this->serializeDepartments($member),
            'ticketStats' => $this->profiles->ticketStatsBundle($member->id, $teamIds, $departmentIds),
            'assignedByStatus' => $this->profiles->assignedTicketsByStatus($member->id),
            'assignedByPriority' => $this->profiles->assignedTicketsByPriority($member->id),
            'recentAssignedTickets' => $this->profiles->recentAssignedTickets($member->id),
            'recentTeamTickets' => $this->profiles->recentTeamTickets($teamIds),
            'recentDepartmentTickets' => $this->profiles->recentDepartmentTickets($departmentIds),
            'performance' => $this->performance->summary($member->id, 30, (float) $member->performance_score),
            'recentPerformanceEvents' => [
                'data' => $this->performance->recentEvents($member->id, 10)->all(),
            ],
            'customFieldDefinitions' => $this->helpdeskSettings->userFieldDefinitions(),
        ];
    }

    private function serializeMember(User $member): array
    {
        return array_merge([
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'performance_score' => $member->performance_score,
            'custom_fields' => $member->custom_fields ?? [],
            'roles' => $member->roles->map(fn ($role) => ['name' => $role->name])->values(),
            'skills' => $member->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
            ])->values(),
            'created_at' => $member->created_at,
        ], AvatarSupport::payload($member));
    }

    private function serializeMemberships(User $member): array
    {
        return $member->teams->map(function ($team) use ($member) {
            return [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'team_slug' => $team->slug,
                'org_role' => $team->pivot->org_role,
                'is_team_lead' => (int) $team->lead_user_id === $member->id,
                'department' => $team->department ? [
                    'id' => $team->department->id,
                    'name' => $team->department->name,
                    'slug' => $team->department->slug,
                    'is_head' => (int) $team->department->head_user_id === $member->id,
                ] : null,
            ];
        })->values()->all();
    }

    private function serializeDepartments(User $member): array
    {
        return $member->teams
            ->pluck('department')
            ->filter()
            ->unique('id')
            ->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'slug' => $department->slug,
                'description' => $department->description,
                'is_head' => (int) $department->head_user_id === $member->id,
                'teams' => $member->teams
                    ->where('department_id', $department->id)
                    ->map(fn ($team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'org_role' => $team->pivot->org_role,
                        'is_lead' => (int) $team->lead_user_id === $member->id,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
