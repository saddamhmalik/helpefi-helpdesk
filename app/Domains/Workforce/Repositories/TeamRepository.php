<?php

namespace App\Domains\Workforce\Repositories;

use App\Domains\Workforce\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TeamRepository
{
    public function find(int $id): Team
    {
        return Team::query()
            ->with(['department:id,name', 'lead:id,name,email', 'members:id,name,email'])
            ->findOrFail($id);
    }

    public function options(?int $departmentId = null): Collection
    {
        return Team::query()
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->with('department:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'department_id', 'name', 'slug']);
    }

    public function create(array $data): Team
    {
        $data['slug'] = $this->uniqueSlug($data['department_id'], $data['slug'] ?? $data['name']);

        return Team::query()->create($data);
    }

    public function update(Team $team, array $data): Team
    {
        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($team->department_id, $data['name'], $team->id);
        }

        $team->update($data);

        return $team->fresh(['department', 'lead', 'members']);
    }

    public function delete(Team $team): void
    {
        $team->delete();
    }

    public function syncMembers(Team $team, array $memberships): Team
    {
        $sync = [];

        foreach ($memberships as $membership) {
            if (empty($membership['user_id'])) {
                continue;
            }

            $sync[(int) $membership['user_id']] = [
                'org_role' => $membership['org_role'] ?? Team::ROLE_MEMBER,
            ];
        }

        $team->members()->sync($sync);

        return $this->find($team->id);
    }

    public function primaryTeamForUser(int $userId): ?Team
    {
        return Team::query()
            ->where('is_active', true)
            ->whereHas('members', fn ($query) => $query->where('users.id', $userId))
            ->with('department')
            ->orderBy('sort_order')
            ->first();
    }

    public function teamLeadForTicket(?int $teamId): ?User
    {
        if (! $teamId) {
            return null;
        }

        $team = Team::query()->with('lead')->find($teamId);

        return $team?->lead;
    }

    public function departmentHeadForTicket(?int $departmentId): ?User
    {
        if (! $departmentId) {
            return null;
        }

        return Team::query()
            ->where('department_id', $departmentId)
            ->first()
            ?->department()
            ->first()
            ?->head;
    }

    private function uniqueSlug(int $departmentId, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'team';
        $slug = $base;
        $counter = 1;

        while ($this->slugExists($departmentId, $slug, $ignoreId)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function slugExists(int $departmentId, string $slug, ?int $ignoreId): bool
    {
        return Team::query()
            ->where('department_id', $departmentId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}
