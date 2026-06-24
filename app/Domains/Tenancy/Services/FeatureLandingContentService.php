<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;

class FeatureLandingContentService
{
    public function __construct(private MarketingContentInterpolator $interpolator)
    {
    }

    public function forSlug(string $slug): ?array
    {
        $content = config("marketing_feature_content.{$slug}");

        if (! is_array($content)) {
            return null;
        }

        return $this->interpolator->interpolate($content);
    }

    public function navigation(): array
    {
        return collect(MarketingFeatureDefinition::slugs())
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
