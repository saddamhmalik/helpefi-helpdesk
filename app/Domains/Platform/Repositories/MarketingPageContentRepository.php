<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\MarketingPageContent;
use Illuminate\Database\Eloquent\Collection;

class MarketingPageContentRepository
{
    public function findPublished(string $pageType, string $slug): ?MarketingPageContent
    {
        return MarketingPageContent::query()
            ->where('page_type', $pageType)
            ->where('slug', $slug)
            ->where('status', MarketingPageContent::STATUS_PUBLISHED)
            ->first();
    }

    public function publishedSlugs(string $pageType): array
    {
        return MarketingPageContent::query()
            ->where('page_type', $pageType)
            ->where('status', MarketingPageContent::STATUS_PUBLISHED)
            ->pluck('slug')
            ->all();
    }

    public function allPublished(): Collection
    {
        return MarketingPageContent::query()
            ->where('status', MarketingPageContent::STATUS_PUBLISHED)
            ->orderBy('page_type')
            ->orderBy('slug')
            ->get();
    }

    public function upsert(string $pageType, string $slug, array $data): MarketingPageContent
    {
        $existing = MarketingPageContent::query()
            ->where('page_type', $pageType)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            $existing->update($data);

            return $existing->fresh();
        }

        return MarketingPageContent::query()->create(array_merge($data, [
            'page_type' => $pageType,
            'slug' => $slug,
        ]));
    }

    public function textCorpus(): array
    {
        return MarketingPageContent::query()
            ->where('status', MarketingPageContent::STATUS_PUBLISHED)
            ->get(['id', 'page_type', 'slug', 'content'])
            ->map(fn (MarketingPageContent $row) => [
                'id' => 'page:'.$row->page_type.':'.$row->slug,
                'title' => (string) ($row->content['nav_label'] ?? $row->slug),
                'text' => $this->extractText($row->content ?? []),
            ])
            ->all();
    }

    private function extractText(array $content): string
    {
        $parts = [];

        array_walk_recursive($content, function ($value) use (&$parts) {
            if (is_string($value) && trim($value) !== '') {
                $parts[] = $value;
            }
        });

        return implode(' ', $parts);
    }
}
