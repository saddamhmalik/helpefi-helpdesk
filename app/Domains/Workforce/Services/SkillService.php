<?php

namespace App\Domains\Workforce\Services;

use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Workforce\Models\Skill;
use App\Domains\Workforce\Repositories\SkillRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class SkillService
{
    public function __construct(
        private SkillRepository $skills,
        private AuditRecorder $audit,
    ) {
    }

    public function all(): Collection
    {
        return $this->skills->all();
    }

    public function options(): array
    {
        return $this->skills->options();
    }

    public function create(string $name): Skill
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Skill name is required.');
        }

        $skill = $this->skills->create($name);

        $this->audit->record('skill.created', $skill, ['name' => $skill->name]);

        return $skill;
    }

    public function update(int $id, string $name): Skill
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Skill name is required.');
        }

        $skill = $this->skills->update($this->skills->find($id), $name);

        $this->audit->record('skill.updated', $skill, ['name' => $skill->name]);

        return $skill;
    }

    public function delete(int $id): void
    {
        $skill = $this->skills->find($id);
        $this->skills->delete($skill);

        $this->audit->record('skill.deleted', $skill, ['name' => $skill->name]);
    }

    public function syncForMember(int $memberId, array $skillIds): void
    {
        $user = User::query()->findOrFail($memberId);
        $this->skills->syncForUser($user, $skillIds);

        $this->audit->record('member.skills_updated', $user, [
            'skill_ids' => $skillIds,
        ]);
    }
}
