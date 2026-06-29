<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Tenancy\Support\IntegrationLandingDefinition;
use App\Domains\Tenancy\Support\MarketingContentInterpolator;

class IntegrationLandingContentService
{
    public function __construct(
        private MarketingPageContentService $pages,
        private MarketingContentInterpolator $interpolator,
    ) {
    }

    public function forSlug(string $slug): ?array
    {
        return $this->pages->resolve(
            MarketingContentType::INTEGRATION,
            'marketing_integration_content',
            $slug
        );
    }

    public function navigation(): array
    {
        return collect($this->pages->slugsForType(MarketingContentType::INTEGRATION))
            ->map(function (string $slug) {
                $content = $this->forSlug($slug);

                if ($content === null) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'path' => IntegrationLandingDefinition::path($slug),
                    'nav_label' => (string) ($content['nav_label'] ?? $slug),
                    'badge' => (string) ($content['badge'] ?? ''),
                    'hero_subtitle' => (string) ($content['hero_subtitle'] ?? ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function hub(): array
    {
        $content = config('marketing_integrations_hub_content', []);

        return is_array($content) ? $this->interpolator->interpolate($content) : [];
    }
}
