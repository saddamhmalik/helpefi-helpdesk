<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Knowledge\Repositories\KnowledgeCollectionRepository;
use App\Domains\Knowledge\Support\PlatformKnowledge;
use Illuminate\Database\Eloquent\Collection;

class PlatformHandbookService
{
    public function __construct(
        private KnowledgeCollectionRepository $collections,
    ) {
    }

    public function collection(): ?KnowledgeCollection
    {
        return KnowledgeCollection::query()
            ->where('slug', PlatformKnowledge::HANDBOOK_COLLECTION_SLUG)
            ->first();
    }

    public function sections(): array
    {
        $collection = $this->collection();

        if (! $collection) {
            return [];
        }

        $articles = KnowledgeArticle::query()
            ->with('category:id,name,slug')
            ->where('knowledge_collection_id', $collection->id)
            ->where('is_published', true)
            ->orderBy('slug')
            ->get(['id', 'title', 'slug', 'excerpt', 'knowledge_category_id', 'is_public']);

        $sectionOrder = collect(PlatformKnowledge::HANDBOOK_SECTIONS)
            ->keyBy('slug');

        $slugOrder = collect(PlatformKnowledge::HANDBOOK_ARTICLE_SLUGS)->flip();

        return $articles
            ->groupBy(fn (KnowledgeArticle $article) => $article->category?->slug ?? 'other')
            ->sortBy(fn ($group, $slug) => $sectionOrder->get($slug)['sort_order'] ?? 99)
            ->map(function (Collection $group, string $slug) use ($sectionOrder, $slugOrder) {
                return [
                    'slug' => $slug,
                    'name' => $sectionOrder->get($slug)['name'] ?? $group->first()?->category?->name ?? 'Guides',
                    'articles' => $group
                        ->sortBy(fn (KnowledgeArticle $article) => $slugOrder->get($article->slug, 999))
                        ->values()
                        ->map(fn (KnowledgeArticle $article) => [
                            'id' => $article->id,
                            'title' => $article->title,
                            'slug' => $article->slug,
                            'excerpt' => $article->excerpt,
                            'is_public' => (bool) $article->is_public,
                        ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function isSystemCollection(KnowledgeCollection $collection): bool
    {
        return (bool) $collection->is_system;
    }

    public function isSystemArticle(KnowledgeArticle $article): bool
    {
        return (bool) $article->is_system;
    }
}
