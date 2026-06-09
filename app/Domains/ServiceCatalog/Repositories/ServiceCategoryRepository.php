<?php

namespace App\Domains\ServiceCatalog\Repositories;

use App\Domains\ServiceCatalog\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ServiceCategoryRepository
{
    public function allWithItems(): Collection
    {
        return ServiceCategory::query()
            ->with(['items.priority:id,name,slug'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function publicWithItems(): Collection
    {
        return ServiceCategory::query()
            ->where('is_active', true)
            ->with(['items' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (ServiceCategory $category) => $category->items->isNotEmpty())
            ->values();
    }

    public function find(int $id): ServiceCategory
    {
        return ServiceCategory::query()->findOrFail($id);
    }

    public function create(array $data): ServiceCategory
    {
        $data['slug'] = $this->uniqueSlug($data['name']);

        return ServiceCategory::query()->create($data);
    }

    public function update(ServiceCategory $category, array $data): ServiceCategory
    {
        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }

        $category->update($data);

        return $category->fresh(['items.priority:id,name,slug']);
    }

    public function delete(ServiceCategory $category): void
    {
        $category->delete();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (
            ServiceCategory::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
