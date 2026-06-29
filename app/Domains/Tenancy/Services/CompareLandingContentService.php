<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Tenancy\Support\CompareLandingDefinition;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;

class CompareLandingContentService
{
    public function __construct(
        private MarketingPageContentService $pages,
        private MarketingContentInterpolator $interpolator,
    ) {
    }

    public function forSlug(string $slug): ?array
    {
        return $this->pages->resolve(
            MarketingContentType::COMPARISON,
            'marketing_comparison_content',
            $slug
        );
    }

    public function navigation(): array
    {
        return collect($this->pages->slugsForType(MarketingContentType::COMPARISON))
            ->map(function (string $slug) {
                $content = $this->forSlug($slug);

                if ($content === null) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'path' => CompareLandingDefinition::path($slug),
                    'nav_label' => (string) ($content['nav_label'] ?? $content['competitor_name'] ?? $slug),
                    'competitor_name' => (string) ($content['competitor_name'] ?? $slug),
                    'footer_label' => 'vs '.(string) ($content['competitor_name'] ?? $content['nav_label'] ?? $slug),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function hub(): array
    {
        $content = config('marketing_comparisons_hub_content', []);

        return is_array($content) ? $this->interpolator->interpolate($content) : [];
    }
}
