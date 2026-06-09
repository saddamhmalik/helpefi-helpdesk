<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Knowledge\Models\KnowledgeCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class KnowledgeCollectionRepository
{
    public function all(): Collection
    {
        return KnowledgeCollection::query()
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function publicList(?int $brandId = null): Collection
    {
        return KnowledgeCollection::query()
            ->where('is_public', true)
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
            ->whereHas('articles', fn ($q) => $q->where('is_published', true))
            ->withCount(['articles' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): KnowledgeCollection
    {
        return KnowledgeCollection::query()->findOrFail($id);
    }

    public function findBySlug(string $slug): KnowledgeCollection
    {
        return KnowledgeCollection::query()->where('slug', $slug)->firstOrFail();
    }

    public function findBySlugForBrand(string $slug, int $brandId): KnowledgeCollection
    {
        return KnowledgeCollection::query()
            ->where('slug', $slug)
            ->where('brand_id', $brandId)
            ->firstOrFail();
    }

    public function create(array $data): KnowledgeCollection
    {
        $data['slug'] = $this->uniqueSlug($data['name']);

        return KnowledgeCollection::query()->create($data);
    }

    public function update(KnowledgeCollection $collection, array $data): KnowledgeCollection
    {
        if (isset($data['name']) && $data['name'] !== $collection->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $collection->id);
        }

        $collection->update($data);

        return $collection->fresh();
    }

    public function delete(KnowledgeCollection $collection): void
    {
        $collection->delete();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (
            KnowledgeCollection::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
