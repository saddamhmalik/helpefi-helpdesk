<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;

class VerticalLandingContentService
{
    public function __construct(private MarketingPageContentService $pages)
    {
    }

    public function forSlug(string $slug): ?array
    {
        return $this->pages->resolve(
            MarketingContentType::VERTICAL,
            'marketing_vertical_content',
            $slug
        );
    }

    public function navigation(): array
    {
        return collect($this->pages->slugsForType(MarketingContentType::VERTICAL))
            ->map(function (string $slug) {
                $content = $this->forSlug($slug);

                if ($content === null) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'path' => VerticalLandingDefinition::path($slug),
                    'nav_label' => (string) ($content['nav_label'] ?? $slug),
                    'badge' => (string) ($content['badge'] ?? ''),
                    'hero_subtitle' => (string) ($content['hero_subtitle'] ?? ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
