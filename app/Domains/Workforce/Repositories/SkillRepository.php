<?php

namespace App\Domains\Workforce\Repositories;

use App\Domains\Workforce\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SkillRepository
{
    public function all(): Collection
    {
        return Skill::query()->orderBy('name')->get();
    }

    public function options(): array
    {
        return $this->all()
            ->map(fn (Skill $skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
            ])
            ->all();
    }

    public function find(int $id): Skill
    {
        return Skill::query()->findOrFail($id);
    }

    public function create(string $name): Skill
    {
        $baseSlug = Str::slug($name) ?: 'skill';
        $slug = $baseSlug;
        $suffix = 1;

        while (Skill::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return Skill::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    public function update(Skill $skill, string $name): Skill
    {
        $skill->update(['name' => $name]);

        return $skill->fresh();
    }

    public function delete(Skill $skill): void
    {
        $skill->delete();
    }

    public function syncForUser(User $user, array $skillIds): void
    {
        $user->skills()->sync($skillIds);
    }

    public function filterAgentIdsBySkills(array $agentIds, array $skillIds): array
    {
        if ($skillIds === [] || $agentIds === []) {
            return $agentIds;
        }

        $query = User::query()->whereIn('id', $agentIds);

        foreach ($skillIds as $skillId) {
            $query->whereHas('skills', fn ($skills) => $skills->where('skills.id', $skillId));
        }

        return $query->orderBy('id')->pluck('id')->all();
    }
}
