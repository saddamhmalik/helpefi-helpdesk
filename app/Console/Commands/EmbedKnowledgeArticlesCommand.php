<?php

namespace App\Console\Commands;

use App\Domains\Knowledge\Services\KnowledgeEmbeddingService;
use Illuminate\Console\Command;

class EmbedKnowledgeArticlesCommand extends Command
{
    protected $signature = 'knowledge:embed-articles';

    protected $description = 'Generate vector embeddings for published knowledge base articles';

    public function handle(KnowledgeEmbeddingService $embeddings): int
    {
        $count = $embeddings->embedAllPublished();

        $this->info("Embedded {$count} published articles.");

        return self::SUCCESS;
    }
}
