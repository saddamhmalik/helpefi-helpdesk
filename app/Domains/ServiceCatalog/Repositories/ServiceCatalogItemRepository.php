<?php

namespace App\Domains\ServiceCatalog\Repositories;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use Illuminate\Support\Str;

class ServiceCatalogItemRepository
{
    public function find(int $id): ServiceCatalogItem
    {
        return ServiceCatalogItem::query()
            ->with(['category:id,name,slug', 'priority:id,name,slug'])
            ->findOrFail($id);
    }

    public function findPublicBySlug(string $slug): ServiceCatalogItem
    {
        return ServiceCatalogItem::query()
            ->with(['category:id,name,slug', 'priority:id,name,slug'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();
    }

    public function create(array $data): ServiceCatalogItem
    {
        $data['slug'] = $this->uniqueSlug($data['name']);

        return ServiceCatalogItem::query()->create($data);
    }

    public function update(ServiceCatalogItem $item, array $data): ServiceCatalogItem
    {
        if (isset($data['name']) && $data['name'] !== $item->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $item->id);
        }

        $item->update($data);

        return $item->fresh(['category:id,name,slug', 'priority:id,name,slug']);
    }

    public function delete(ServiceCatalogItem $item): void
    {
        $item->delete();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (
            ServiceCatalogItem::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
