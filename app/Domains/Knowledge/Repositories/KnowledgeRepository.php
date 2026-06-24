<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCategory;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Knowledge\Support\KnowledgePortalVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
        $locale = $data['locale'] ?? 'en';
        $data['locale'] = $locale;
        $data['slug'] = $this->uniqueSlug($data['title'], $locale);
        $data['translation_group_id'] = $data['translation_group_id'] ?? (string) Str::uuid();

        return KnowledgeArticle::query()->create($data);
    }

    public function update(KnowledgeArticle $article, array $data): KnowledgeArticle
    {
        $locale = $data['locale'] ?? $article->locale;

        if (isset($data['title']) && $data['title'] !== $article->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $locale, $article->id);
        }

        $article->update($data);

        return $article->fresh(['category', 'collection', 'author']);
    }

    public function categories(): Collection
    {
        return KnowledgeCategory::query()->orderBy('name')->get();
    }

    public function publishedCount(?string $locale = null): int
    {
        return KnowledgeArticle::query()
            ->where('is_published', true)
            ->when($locale, fn ($q) => $q->where('locale', $locale))
            ->count();
    }

    public function publishedPaginate(
        ?int $collectionId = null,
        ?string $search = null,
        int $perPage = 15,
        ?int $brandId = null,
        ?string $locale = null,
    ): LengthAwarePaginator {
        return KnowledgeArticle::query()
            ->with(['category:id,name', 'collection:id,name,slug'])
            ->tap(fn (Builder $query) => $this->applyPortalVisibilityScope($query))
            ->when($locale, fn ($q) => $q->where('locale', $locale))
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

    public function findPublishedBySlug(string $slug, ?int $brandId = null, ?string $locale = null): KnowledgeArticle
    {
        $article = $this->publishedBySlugQuery($slug, $brandId, $locale)->first();

        if (! $article && $locale) {
            $article = $this->publishedBySlugQuery($slug, $brandId, null)->first();
        }

        if (! $article) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(KnowledgeArticle::class, [$slug]);
        }

        return $article;
    }

    public function featuredPublished(int $limit = 6, ?int $brandId = null, ?string $locale = null): Collection
    {
        return KnowledgeArticle::query()
            ->with(['collection:id,name,slug'])
            ->tap(fn (Builder $query) => $this->applyPortalVisibilityScope($query))
            ->when($locale, fn ($q) => $q->where('locale', $locale))
            ->when($brandId, fn ($q) => $q->whereHas('collection', fn ($c) => $c->where('brand_id', $brandId)))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'excerpt', 'locale', 'knowledge_collection_id', 'published_at']);
    }

    public function translations(KnowledgeArticle $article): Collection
    {
        if (! $article->translation_group_id) {
            return KnowledgeArticle::query()
                ->where('id', $article->id)
                ->get(['id', 'locale', 'slug', 'title', 'is_published']);
        }

        return KnowledgeArticle::query()
            ->where('translation_group_id', $article->translation_group_id)
            ->orderBy('locale')
            ->get(['id', 'locale', 'slug', 'title', 'is_published']);
    }

    public function portalTranslations(KnowledgeArticle $article, ?int $brandId = null): Collection
    {
        if (! $article->translation_group_id) {
            $query = KnowledgeArticle::query()
                ->where('id', $article->id)
                ->tap(fn (Builder $query) => $this->applyPortalVisibilityScope($query));

            if ($brandId) {
                $query->whereIn(
                    'knowledge_collection_id',
                    KnowledgeCollection::query()->where('brand_id', $brandId)->select('id'),
                );
            }

            return $query->get(['id', 'locale', 'slug', 'title', 'is_published']);
        }

        return KnowledgeArticle::query()
            ->where('translation_group_id', $article->translation_group_id)
            ->tap(fn (Builder $query) => $this->applyPortalVisibilityScope($query))
            ->when($brandId, fn (Builder $query) => $query->whereIn(
                'knowledge_collection_id',
                KnowledgeCollection::query()->where('brand_id', $brandId)->select('id'),
            ))
            ->orderBy('locale')
            ->get(['id', 'locale', 'slug', 'title', 'is_published']);
    }

    public function findByGroupAndLocale(string $groupId, string $locale): ?KnowledgeArticle
    {
        return KnowledgeArticle::query()
            ->where('translation_group_id', $groupId)
            ->where('locale', $locale)
            ->first();
    }

    private function uniqueSlug(string $title, string $locale, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (
            KnowledgeArticle::query()
                ->where('locale', $locale)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function publishedBySlugQuery(string $slug, ?int $brandId = null, ?string $locale = null): Builder
    {
        return KnowledgeArticle::query()
            ->with(['category:id,name', 'collection:id,name,slug'])
            ->where('slug', $slug)
            ->tap(fn (Builder $query) => $this->applyPortalVisibilityScope($query))
            ->when($locale, fn ($q) => $q->where('locale', $locale))
            ->when($brandId, fn ($query) => $query->whereIn(
                'knowledge_collection_id',
                KnowledgeCollection::query()->where('brand_id', $brandId)->select('id'),
            ));
    }

    private function applyPortalVisibilityScope(Builder $query): void
    {
        KnowledgePortalVisibility::applyPortal($query);
    }
}
