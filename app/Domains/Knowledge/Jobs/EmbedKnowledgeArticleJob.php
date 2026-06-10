<?php

namespace App\Domains\Knowledge\Jobs;

use App\Domains\Knowledge\Services\KnowledgeEmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedKnowledgeArticleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $articleId)
    {
    }

    public function handle(KnowledgeEmbeddingService $embeddings): void
    {
        $embeddings->embedArticle($this->articleId);
    }
}
