<?php

namespace App\Domains\Knowledge\Repositories;

use App\Domains\Knowledge\Models\KnowledgeArticle;
use App\Domains\Knowledge\Models\KnowledgeArticleVersion;
use Illuminate\Database\Eloquent\Collection;

class KnowledgeVersionRepository
{
    public function forArticle(int $articleId): Collection
    {
        return KnowledgeArticleVersion::query()
            ->where('knowledge_article_id', $articleId)
            ->with('user:id,name')
            ->orderByDesc('version_number')
            ->get();
    }

    public function findForArticle(int $articleId, int $versionId): KnowledgeArticleVersion
    {
        return KnowledgeArticleVersion::query()
            ->where('knowledge_article_id', $articleId)
            ->findOrFail($versionId);
    }

    public function snapshot(KnowledgeArticle $article, int $userId): KnowledgeArticleVersion
    {
        $nextVersion = (int) KnowledgeArticleVersion::query()
            ->where('knowledge_article_id', $article->id)
            ->max('version_number') + 1;

        return KnowledgeArticleVersion::query()->create([
            'knowledge_article_id' => $article->id,
            'user_id' => $userId,
            'version_number' => $nextVersion,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
        ]);
    }
}
