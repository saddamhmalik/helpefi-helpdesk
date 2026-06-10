<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Services\KnowledgeEmbeddingService;
use App\Domains\Ai\Contracts\AiEmbeddingClient;
use Illuminate\Database\Eloquent\Collection;

class KnowledgeSearchRepository
{
    private const STOP_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
        'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
        'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'i',
        'me', 'my', 'we', 'our', 'you', 'your', 'it', 'its', 'this', 'that', 'these', 'those',
        'how', 'what', 'when', 'where', 'why', 'who', 'which', 'not', 'no', 'yes', 'get', 'need',
        'help', 'please', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below',
    ];

    public function __construct(
        private AiEmbeddingClient $embeddingClient,
        private KnowledgeEmbeddingRepository $embeddings,
        private KnowledgeEmbeddingService $embeddingService,
    ) {
    }

    public function searchPublished(string $query, int $limit = 5, ?int $brandId = null, ?string $locale = null): Collection
    {
        $normalized = $this->normalizeQuery($query);

        if ($normalized === '') {
            return new Collection;
        }

        if ($this->embeddingClient->available()) {
            $vectorResults = $this->vectorSearch($normalized, $limit, $brandId, $locale);

            if ($vectorResults->isNotEmpty()) {
                return $vectorResults;
            }
        }

        return $this->keywordSearch($normalized, $limit, $brandId, $locale);
    }

    private function vectorSearch(string $query, int $limit, ?int $brandId, ?string $locale): Collection
    {
        try {
            $queryVector = $this->embeddingClient->embed($query);
        } catch (\Throwable) {
            return new Collection;
        }

        $articles = $this->embeddings->publishedWithEmbeddings($brandId, $locale);

        $scored = $articles->map(function (KnowledgeArticle $article) use ($queryVector) {
            $embedding = $article->embedding?->embedding ?? [];

            return [
                'article' => $article,
                'score' => $this->embeddingService->cosineSimilarity($queryVector, $embedding),
            ];
        })
            ->filter(fn (array $row) => $row['score'] > 0.65)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return new Collection($scored->map(fn (array $row) => $row['article'])->all());
    }

    private function keywordSearch(string $normalized, int $limit, ?int $brandId, ?string $locale): Collection
    {
        $terms = $this->extractTerms($normalized);

        if ($terms->isEmpty()) {
            return new Collection;
        }

        $articles = KnowledgeArticle::query()
            ->where('is_published', true)
            ->when($locale, fn ($q) => $q->where('locale', $locale))
            ->when($brandId, fn ($q) => $q->whereHas('collection', fn ($c) => $c->where('brand_id', $brandId)))
            ->get(['id', 'title', 'slug', 'excerpt', 'body', 'locale']);

        $scored = $articles->map(function (KnowledgeArticle $article) use ($normalized, $terms) {
            return [
                'article' => $article,
                'score' => $this->scoreArticle($article, $normalized, $terms),
            ];
        })
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return new Collection($scored->map(fn (array $row) => $row['article'])->all());
    }

    private function normalizeQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = html_entity_decode($query, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $query = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));

        return $query;
    }

    private function extractTerms(string $query): \Illuminate\Support\Collection
    {
        return collect(preg_split('/\s+/u', $query) ?: [])
            ->map(fn ($term) => trim($term, ".,!?;:'\"()[]{}"))
            ->filter(fn ($term) => mb_strlen($term) >= 2 && ! in_array($term, self::STOP_WORDS, true))
            ->unique()
            ->values();
    }

    private function scoreArticle(KnowledgeArticle $article, string $query, \Illuminate\Support\Collection $terms): float
    {
        $title = mb_strtolower((string) $article->title);
        $excerpt = mb_strtolower(strip_tags((string) ($article->excerpt ?? '')));
        $body = mb_strtolower(strip_tags((string) ($article->body ?? '')));
        $score = 0.0;

        if ($title !== '' && str_contains($title, $query)) {
            $score += 40;
        }

        if ($excerpt !== '' && str_contains($excerpt, $query)) {
            $score += 25;
        }

        if ($body !== '' && str_contains($body, $query)) {
            $score += 15;
        }

        foreach ($terms as $term) {
            if (str_contains($title, $term)) {
                $score += 12;
            }

            if ($excerpt !== '' && str_contains($excerpt, $term)) {
                $score += 6;
            }

            if ($body !== '' && str_contains($body, $term)) {
                $score += 2;
            }
        }

        $bigrams = $this->bigrams($terms);

        foreach ($bigrams as $bigram) {
            if (str_contains($title, $bigram)) {
                $score += 8;
            }

            if ($excerpt !== '' && str_contains($excerpt, $bigram)) {
                $score += 4;
            }

            if ($body !== '' && str_contains($body, $bigram)) {
                $score += 1.5;
            }
        }

        return $score;
    }

    private function bigrams(\Illuminate\Support\Collection $terms): array
    {
        $bigrams = [];

        for ($i = 0; $i < $terms->count() - 1; $i++) {
            $bigrams[] = $terms[$i].' '.$terms[$i + 1];
        }

        return $bigrams;
    }
}
