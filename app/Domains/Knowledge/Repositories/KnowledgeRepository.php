<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class KnowledgeRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return KnowledgeArticle::query()
            ->with(['category:id,name', 'collection:id,name', 'author:id,name'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function find(int $id): KnowledgeArticle
    {
        return KnowledgeArticle::query()
            ->with(['category', 'collection', 'author:id,name', 'versions.user:id,name'])
            ->findOrFail($id);
    }

    public function create(array $data): KnowledgeArticle
    {
        $data['slug'] = $this->uniqueSlug($data['title']);

        return KnowledgeArticle::query()->create($data);
    }

    public function update(KnowledgeArticle $article, array $data): KnowledgeArticle
    {
        if (isset($data['title']) && $data['title'] !== $article->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $article->id);
        }

        $article->update($data);

        return $article->fresh(['category', 'collection', 'author']);
    }

    public function categories(): Collection
    {
        return KnowledgeCategory::query()->orderBy('name')->get();
    }

    public function publishedCount(): int
    {
        return KnowledgeArticle::query()->where('is_published', true)->count();
    }

    public function publishedPaginate(?int $collectionId = null, ?string $search = null, int $perPage = 15, ?int $brandId = null): LengthAwarePaginator
    {
        return KnowledgeArticle::query()
            ->with(['category:id,name', 'collection:id,name,slug'])
            ->where('is_published', true)
            ->when($collectionId, fn ($q) => $q->where('knowledge_collection_id', $collectionId))
            ->when($brandId, fn ($q) => $q->whereHas('collection', fn ($c) => $c->where('brand_id', $brandId)))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            }))
            ->orderBy('title')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublishedBySlug(string $slug, ?int $brandId = null): KnowledgeArticle
    {
        return KnowledgeArticle::query()
            ->with(['category:id,name', 'collection:id,name,slug'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->when($brandId, fn ($query) => $query->whereIn(
                'knowledge_collection_id',
                KnowledgeCollection::query()->where('brand_id', $brandId)->select('id'),
            ))
            ->firstOrFail();
    }

    public function featuredPublished(int $limit = 6, ?int $brandId = null): Collection
    {
        return KnowledgeArticle::query()
            ->with(['collection:id,name,slug'])
            ->where('is_published', true)
            ->when($brandId, fn ($q) => $q->whereHas('collection', fn ($c) => $c->where('brand_id', $brandId)))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'excerpt', 'knowledge_collection_id', 'published_at']);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (
            KnowledgeArticle::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
