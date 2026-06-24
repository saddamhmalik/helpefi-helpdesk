<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeArticleEmbedding;
use App\Domains\Knowledge\Support\KnowledgePortalVisibility;
use Illuminate\Database\Eloquent\Collection;

class KnowledgeEmbeddingRepository
{
    public function upsert(KnowledgeArticle $article, string $model, array $embedding): KnowledgeArticleEmbedding
    {
        return KnowledgeArticleEmbedding::query()->updateOrCreate(
            ['knowledge_article_id' => $article->id],
            ['model' => $model, 'embedding' => $embedding],
        );
    }

    public function publishedWithEmbeddings(?int $brandId = null, ?string $locale = null, bool $portalOnly = false): Collection
    {
        return KnowledgeArticle::query()
            ->tap(fn ($query) => $portalOnly
                ? KnowledgePortalVisibility::applyPortal($query)
                : KnowledgePortalVisibility::applyPublished($query))
            ->whereHas('embedding')
            ->when($locale, fn ($q) => $q->where('locale', $locale))
            ->when($brandId, fn ($q) => $q->whereHas('collection', fn ($c) => $c->where('brand_id', $brandId)))
            ->with('embedding')
            ->get(['id', 'title', 'slug', 'excerpt', 'body', 'locale', 'is_published', 'is_public']);
    }
}
