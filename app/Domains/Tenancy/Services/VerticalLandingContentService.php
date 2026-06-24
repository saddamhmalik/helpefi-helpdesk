<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;

class VerticalLandingContentService
{
    public function __construct(private MarketingContentInterpolator $interpolator)
    {
    }

    public function forSlug(string $slug): ?array
    {
        $content = config("marketing_vertical_content.{$slug}");

        if (! is_array($content)) {
            return null;
        }

        return $this->interpolator->interpolate($content);
    }

    public function navigation(): array
    {
        return collect(VerticalLandingDefinition::slugs())
            ->map(function (string $slug) {
                $content = $this->forSlug($slug);

                if ($content === null) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'path' => VerticalLandingDefinition::path($slug),
                    'nav_label' => (string) ($content['nav_label'] ?? $slug),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
