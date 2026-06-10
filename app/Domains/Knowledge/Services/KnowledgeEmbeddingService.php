<?php

namespace App\Domains\Knowledge\Services;

use App\Domains\Ai\Contracts\AiEmbeddingClient;
use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Repositories\KnowledgeEmbeddingRepository;
use App\Domains\Knowledge\Repositories\KnowledgeRepository;

class KnowledgeEmbeddingService
{
    public function __construct(
        private KnowledgeRepository $articles,
        private KnowledgeEmbeddingRepository $embeddings,
        private AiEmbeddingClient $client,
    ) {
    }

    public function embedArticle(int $articleId): bool
    {
        if (! $this->client->available()) {
            return false;
        }

        $article = $this->articles->find($articleId);

        if (! $article->is_published) {
            return false;
        }

        $text = $this->composeText($article);
        $vector = $this->client->embed($text);

        $this->embeddings->upsert($article, config('ai.embedding_model'), $vector);

        return true;
    }

    public function embedAllPublished(): int
    {
        $count = 0;

        KnowledgeArticle::query()
            ->where('is_published', true)
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                if ($this->embedArticle($id)) {
                    $count++;
                }
            });

        return $count;
    }

    public function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $other = $b[$i] ?? 0;
            $dot += $value * $other;
            $normA += $value ** 2;
            $normB += $other ** 2;
        }

        if ($normA <= 0 || $normB <= 0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    private function composeText(KnowledgeArticle $article): string
    {
        return trim(implode("\n\n", array_filter([
            $article->title,
            strip_tags((string) $article->excerpt),
            strip_tags((string) $article->body),
        ])));
    }
}
