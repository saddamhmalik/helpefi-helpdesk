<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;

class FeatureLandingContentService
{
    public function __construct(private MarketingPageContentService $pages)
    {
    }

    public function forSlug(string $slug): ?array
    {
        return $this->pages->resolve(
            MarketingContentType::FEATURE,
            'marketing_feature_content',
            $slug
        );
    }

    public function navigation(): array
    {
        return collect($this->pages->slugsForType(MarketingContentType::FEATURE))
            ->map(function (string $slug) {
                $content = $this->forSlug($slug);

                if ($content === null) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'path' => MarketingFeatureDefinition::path($slug),
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
