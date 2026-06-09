<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeArticleVersion;
use App\Domains\Knowledge\Models\KnowledgeCollection;
use App\Domains\Knowledge\Repositories\KnowledgeCollectionRepository;
use App\Domains\Knowledge\Repositories\KnowledgeRepository;
use App\Domains\Knowledge\Repositories\KnowledgeVersionRepository;
use App\Domains\Security\Support\AuditRecorder;
use App\Domains\Tickets\Support\MessageBodySanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class KnowledgeService
{
    public function __construct(
        private KnowledgeRepository $articles,
        private KnowledgeVersionRepository $versions,
        private KnowledgeCollectionRepository $collections,
        private AuditRecorder $audit,
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

        return $article;
    }

    public function update(int $id, array $data, int $userId): KnowledgeArticle
    {
        $article = $this->articles->find($id);

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
        return $this->collections->update($this->collections->find($id), $data);
    }

    public function deleteCollection(int $id): void
    {
        $this->collections->delete($this->collections->find($id));
    }

    public function collectionBySlug(string $slug): KnowledgeCollection
    {
        return $this->collections->findBySlug($slug);
    }

    public function collectionBySlugForBrand(string $slug, int $brandId): KnowledgeCollection
    {
        return $this->collections->findBySlugForBrand($slug, $brandId);
    }

    public function publishedArticles(?int $collectionId = null, ?string $search = null, int $perPage = 15, ?int $brandId = null): LengthAwarePaginator
    {
        return $this->articles->publishedPaginate($collectionId, $search, $perPage, $brandId);
    }

    public function publishedArticleBySlug(string $slug, ?int $brandId = null): KnowledgeArticle
    {
        return $this->articles->findPublishedBySlug($slug, $brandId);
    }

    public function featuredPublished(int $limit = 6, ?int $brandId = null): Collection
    {
        return $this->articles->featuredPublished($limit, $brandId);
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
}
