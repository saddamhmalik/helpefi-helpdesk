<?php

namespace App\Domains\Assignment\Repositories;

use App\Domains\Assignment\Models\AssignmentRule;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Services\TicketStatusLookup;
use App\Domains\Workforce\Models\Team;
use App\Domains\Workforce\Repositories\SkillRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssignmentRuleRepository
{
    public function __construct(
        private SkillRepository $skills,
        private TicketStatusLookup $statusLookup,
    ) {
    }

    public function all(): Collection
    {
        return AssignmentRule::query()
            ->with(['team:id,name', 'department:id,name', 'priority:id,name'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function activeOrdered(): Collection
    {
        return AssignmentRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): AssignmentRule
    {
        return AssignmentRule::query()->findOrFail($id);
    }

    public function create(array $data): AssignmentRule
    {
        return AssignmentRule::query()->create($data);
    }

    public function update(AssignmentRule $rule, array $data): AssignmentRule
    {
        $rule->update($data);

        return $rule->fresh(['team:id,name', 'department:id,name', 'priority:id,name']);
    }

    public function delete(AssignmentRule $rule): void
    {
        $rule->delete();
    }

    public function updateLastAssigned(AssignmentRule $rule, int $userId): void
    {
        $rule->update(['last_assigned_user_id' => $userId]);
    }

    public function candidateAgentIds(AssignmentRule $rule): array
    {
        $candidates = match (true) {
            (bool) $rule->team_id => $this->agentIdsForTeam($rule->team_id),
            (bool) $rule->department_id => $this->agentIdsForDepartment($rule->department_id),
            default => User::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']))
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        };

        return $this->skills->filterAgentIdsBySkills($candidates, $rule->skill_ids ?? []);
    }

    public function openTicketCountsForAgents(array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }

        return Ticket::query()
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->whereIn('assigned_to', $agentIds)
            ->whereNull('merged_into_ticket_id')
            ->tap(fn ($query) => $this->statusLookup->restrictToOpenStatusRelation($query))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function agentIdsForTeam(int $teamId): array
    {
        $team = Team::query()->with(['members' => fn ($query) => $query
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'agent']))
            ->orderBy('users.id')])
            ->find($teamId);

        return $team?->members->pluck('id')->all() ?? [];
    }

    private function agentIdsForDepartment(int $departmentId): array
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'agent']))
            ->whereHas('teams', fn ($query) => $query
                ->where('department_id', $departmentId)
                ->where('is_active', true))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
