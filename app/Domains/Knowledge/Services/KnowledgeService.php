<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Knowledge\Jobs\EmbedKnowledgeArticleJob;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeArticleVersion;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Knowledge\Repositories\KnowledgeCollectionRepository;
use App\Domains\Knowledge\Repositories\KnowledgeRepository;
use App\Domains\Knowledge\Repositories\KnowledgeSettingRepository;
use App\Domains\Knowledge\Repositories\KnowledgeVersionRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class KnowledgeService
{
    public function __construct(
        private KnowledgeRepository $articles,
        private KnowledgeVersionRepository $versions,
        private KnowledgeCollectionRepository $collections,
        private KnowledgeSettingRepository $settings,
        private AuditRecorder $audit,
        private PlatformHandbookService $handbook,
    ) {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->articles->paginate($perPage);
    }

    public function show(int $id): KnowledgeArticle
    {
        return $this->articles->find($id);
    }

    public function create(array $data, int $authorId): KnowledgeArticle
    {
        $data['author_id'] = $authorId;
        $data['locale'] = $this->normalizeLocale($data['locale'] ?? null);

        if (! empty($data['is_published'])) {
            $data['published_at'] = now();
        }

        if (array_key_exists('body', $data)) {
            $data['body'] = $this->normalizeBody($data['body']);
        }

        $article = $this->articles->create($data);

        $this->audit->record('knowledge.article_created', $article, [
            'title' => $article->title,
            'slug' => $article->slug,
        ], $authorId);

        if ($article->is_published) {
            EmbedKnowledgeArticleJob::dispatch($article->id)->afterResponse();
        }

        return $article;
    }

    public function update(int $id, array $data, int $userId): KnowledgeArticle
    {
        $article = $this->articles->find($id);

        if ($this->handbook->isSystemArticle($article)) {
            unset($data['knowledge_collection_id'], $data['slug']);

            if (array_key_exists('is_public', $data) && ! auth()->user()?->hasRole('admin')) {
                unset($data['is_public']);
            }
        }

        if ($this->shouldVersion($article, $data)) {
            $this->versions->snapshot($article, $userId);
        }

        if (array_key_exists('is_published', $data)) {
            $data['published_at'] = $data['is_published'] ? ($article->published_at ?? now()) : null;
        }

        if (array_key_exists('body', $data)) {
            $data['body'] = $this->normalizeBody($data['body']);
        }

        $article = $this->articles->update($article, $data);

        $this->audit->record('knowledge.article_updated', $article, [
            'title' => $article->title,
            'slug' => $article->slug,
        ], $userId);

        if ($article->is_published) {
            EmbedKnowledgeArticleJob::dispatch($article->id)->afterResponse();
        }

        return $article;
    }

    public function restoreVersion(int $articleId, int $versionId, int $userId): KnowledgeArticle
    {
        $article = $this->articles->find($articleId);
        $version = $this->versions->findForArticle($articleId, $versionId);

        $this->versions->snapshot($article, $userId);

        return $this->articles->update($article, [
            'title' => $version->title,
            'excerpt' => $version->excerpt,
            'body' => $version->body,
        ]);
    }

    public function categories(): Collection
    {
        return $this->articles->categories();
    }

    public function collections(): Collection
    {
        return $this->collections->all();
    }

    public function publicCollections(?int $brandId = null): Collection
    {
        return $this->collections->publicList($brandId);
    }

    public function createCollection(array $data): KnowledgeCollection
    {
        return $this->collections->create($data);
    }

    public function updateCollection(int $id, array $data): KnowledgeCollection
    {
        $collection = $this->collections->find($id);

        if ($this->handbook->isSystemCollection($collection)) {
            unset($data['slug'], $data['is_public']);
        }

        return $this->collections->update($collection, $data);
    }

    public function deleteCollection(int $id): void
    {
        $collection = $this->collections->find($id);

        if ($this->handbook->isSystemCollection($collection)) {
            throw new InvalidArgumentException('This collection is part of the platform handbook and cannot be deleted.');
        }

        $this->collections->delete($collection);
    }

    public function collectionBySlug(string $slug): KnowledgeCollection
    {
        return $this->collections->findBySlug($slug);
    }

    public function collectionBySlugForBrand(string $slug, int $brandId): KnowledgeCollection
    {
        return $this->collections->findBySlugForBrand($slug, $brandId);
    }

    public function publishedArticles(
        ?int $collectionId = null,
        ?string $search = null,
        int $perPage = 15,
        ?int $brandId = null,
        ?string $locale = null,
    ): LengthAwarePaginator {
        return $this->articles->publishedPaginate($collectionId, $search, $perPage, $brandId, $locale);
    }

    public function publishedArticleBySlug(string $slug, ?int $brandId = null, ?string $locale = null): KnowledgeArticle
    {
        return $this->articles->findPublishedBySlug($slug, $brandId, $locale);
    }

    public function featuredPublished(int $limit = 6, ?int $brandId = null, ?string $locale = null): Collection
    {
        return $this->articles->featuredPublished($limit, $brandId, $locale);
    }

    public function translations(int $articleId): Collection
    {
        return $this->articles->translations($this->articles->find($articleId));
    }

    public function createTranslation(int $sourceArticleId, string $locale, array $data, int $authorId): KnowledgeArticle
    {
        $source = $this->articles->find($sourceArticleId);
        $locale = $this->normalizeLocale($locale);

        if ($this->articles->findByGroupAndLocale((string) $source->translation_group_id, $locale)) {
            throw new InvalidArgumentException('A translation already exists for this locale.');
        }

        $payload = array_merge([
            'knowledge_category_id' => $source->knowledge_category_id,
            'knowledge_collection_id' => $source->knowledge_collection_id,
            'translation_group_id' => $source->translation_group_id,
            'locale' => $locale,
        ], $data);

        return $this->create($payload, $authorId);
    }

    public function enabledLocales(): array
    {
        return $this->settings->current()->kb_locales ?? ['en'];
    }

    public function versions(int $articleId): Collection
    {
        return $this->versions->forArticle($articleId);
    }

    public function publishedCount(): int
    {
        return $this->articles->publishedCount();
    }

    private function shouldVersion(KnowledgeArticle $article, array $data): bool
    {
        foreach (['title', 'excerpt', 'body'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== $article->{$field}) {
                return true;
            }
        }

        return false;
    }

    private function normalizeBody(string $body): string
    {
        return MessageBodySanitizer::sanitize($body);
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) ($locale ?: ($this->settings->current()->kb_default_locale ?? 'en'))));
        $enabled = $this->settings->current()->kb_locales ?? ['en'];

        if (! in_array($locale, $enabled, true)) {
            throw new InvalidArgumentException('Locale is not enabled for the knowledge base.');
        }

        return $locale;
    }
}
