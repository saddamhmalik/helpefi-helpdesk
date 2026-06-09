<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\PlatformEmailTemplate;
use Illuminate\Database\Eloquent\Collection;

class PlatformEmailTemplateRepository
{
    public function all(): Collection
    {
        return PlatformEmailTemplate::query()
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): PlatformEmailTemplate
    {
        return PlatformEmailTemplate::query()->findOrFail($id);
    }

    public function findBySlug(string $slug): ?PlatformEmailTemplate
    {
        return PlatformEmailTemplate::query()->where('slug', $slug)->first();
    }

    public function activeBySlug(string $slug): ?PlatformEmailTemplate
    {
        return PlatformEmailTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function create(array $data): PlatformEmailTemplate
    {
        return PlatformEmailTemplate::query()->create($data);
    }

    public function update(PlatformEmailTemplate $template, array $data): PlatformEmailTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    public function delete(PlatformEmailTemplate $template): void
    {
        $template->delete();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return PlatformEmailTemplate::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
