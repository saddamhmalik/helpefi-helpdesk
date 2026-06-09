<?php

namespace App\Domains\Workforce\Repositories;

use App\Domains\Workforce\Models\Department;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class DepartmentRepository
{
    public function allWithTeams(): Collection
    {
        return Department::query()
            ->with([
                'head:id,name,email',
                'teams' => fn ($query) => $query->with([
                    'lead:id,name,email',
                    'members:id,name,email',
                ])->withCount(['members', 'tickets']),
            ])
            ->withCount('teams')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function options(): Collection
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function find(int $id): Department
    {
        return Department::query()->findOrFail($id);
    }

    public function create(array $data): Department
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);

        return Department::query()->create($data);
    }

    public function update(Department $department, array $data): Department
    {
        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['name'], $department->id);
        }

        $department->update($data);

        return $department->fresh(['head', 'teams.lead', 'teams.members']);
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'department';
        $slug = $base;
        $counter = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId): bool
    {
        return Department::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}
