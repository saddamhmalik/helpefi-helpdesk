<?php

namespace App\Domains\Workforce\Services;

use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Workforce\Models\Team;
use App\Domains\Workforce\Repositories\DepartmentRepository;
use App\Domains\Workforce\Repositories\TeamRepository;
use App\Domains\Tickets\Support\AssignableAgentCache;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class WorkforceService
{
    public function __construct(
        private DepartmentRepository $departments,
        private TeamRepository $teams,
        private AuditRecorder $audit,
    ) {
    }

    public function catalog(): Collection
    {
        return $this->departments->allWithTeams();
    }

    public function departmentOptions(): Collection
    {
        return $this->departments->options();
    }

    public function teamOptions(?int $departmentId = null): Collection
    {
        return $this->teams->options($departmentId);
    }

    public function agentOptions(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'performance_score']);
    }

    public function assignableAgentIds(): array
    {
        return AssignableAgentCache::remember(
            fn () => $this->agentOptions()->pluck('id')->all(),
        );
    }

    public function meta(): array
    {
        return [
            'org_roles' => [
                ['value' => Team::ROLE_MEMBER, 'label' => 'Member'],
                ['value' => Team::ROLE_TEAM_LEAD, 'label' => 'Team lead'],
            ],
        ];
    }

    public function createDepartment(array $data): Collection
    {
        $department = $this->departments->create($this->validatedDepartment($data));

        $this->audit->record('workforce.department_created', $department, [
            'name' => $department->name,
        ]);


        return $this->catalog();
    }

    public function updateDepartment(int $id, array $data): Collection
    {
        $department = $this->departments->update(
            $this->departments->find($id),
            $this->validatedDepartment($data, false),
        );

        $this->audit->record('workforce.department_updated', $department, [
            'name' => $department->name,
        ]);


        return $this->catalog();
    }

    public function deleteDepartment(int $id): Collection
    {
        $department = $this->departments->find($id);
        $this->departments->delete($department);

        $this->audit->record('workforce.department_deleted', null, [
            'name' => $department->name,
        ]);


        return $this->catalog();
    }

    public function createTeam(array $data): Collection
    {
        $team = $this->teams->create($this->validatedTeam($data));

        if (! empty($data['members'])) {
            $this->teams->syncMembers($team, $data['members']);
        }

        $this->audit->record('workforce.team_created', $team, [
            'name' => $team->name,
            'department_id' => $team->department_id,
        ]);


        return $this->catalog();
    }

    public function updateTeam(int $id, array $data): Collection
    {
        $team = $this->teams->find($id);
        $team = $this->teams->update($team, $this->validatedTeam($data, false));

        if (array_key_exists('members', $data)) {
            $this->teams->syncMembers($team, $data['members'] ?? []);
        }

        $this->audit->record('workforce.team_updated', $team, [
            'name' => $team->name,
        ]);


        return $this->catalog();
    }

    public function deleteTeam(int $id): Collection
    {
        $team = $this->teams->find($id);
        $this->teams->delete($team);

        $this->audit->record('workforce.team_deleted', null, [
            'name' => $team->name,
        ]);


        return $this->catalog();
    }

    public function resolveRoutingForAssignee(?int $userId): array
    {
        if (! $userId) {
            return ['department_id' => null, 'team_id' => null];
        }

        $team = $this->teams->primaryTeamForUser($userId);

        if (! $team) {
            return ['department_id' => null, 'team_id' => null];
        }

        return [
            'department_id' => $team->department_id,
            'team_id' => $team->id,
        ];
    }

    private function validatedDepartment(array $data, bool $requireName = true): array
    {
        if ($requireName && empty($data['name'])) {
            throw new InvalidArgumentException('Department name is required.');
        }

        return [
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'head_user_id' => $data['head_user_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function validatedTeam(array $data, bool $requireName = true): array
    {
        if ($requireName && empty($data['name'])) {
            throw new InvalidArgumentException('Team name is required.');
        }

        if ($requireName && empty($data['department_id'])) {
            throw new InvalidArgumentException('Department is required.');
        }

        return array_filter([
            'department_id' => $data['department_id'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'lead_user_id' => $data['lead_user_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ], fn ($value) => $value !== null);
    }
}
