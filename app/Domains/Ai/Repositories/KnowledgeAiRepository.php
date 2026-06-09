<?php

namespace App\Domains\Ai\Repositories;

use App\Domains\Knowledge\Repositories\KnowledgeSearchRepository;
use Illuminate\Database\Eloquent\Collection;

class KnowledgeAiRepository
{
    public function __construct(private KnowledgeSearchRepository $search)
    {
    }

    public function searchPublished(string $query, int $limit = 5, ?int $brandId = null): Collection
    {
        return $this->search->searchPublished($query, $limit, $brandId);
    }
}
