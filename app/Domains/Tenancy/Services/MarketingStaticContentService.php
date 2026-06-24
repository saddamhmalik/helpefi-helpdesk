<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;

class MarketingStaticContentService
{
    public function __construct(private MarketingContentInterpolator $interpolator)
    {
    }

    public function forSlug(string $slug, ?string $contactEmail = null): ?array
    {
        $content = config("marketing_static_content.{$slug}");

        if (! is_array($content)) {
            return null;
        }

        $extra = [];

        if (is_string($contactEmail) && $contactEmail !== '') {
            $extra['contactEmail'] = $contactEmail;
        }

        return $this->interpolator->with($extra)->interpolate($content);
    }

    public function contact(?string $contactEmail = null): ?array
    {
        return $this->forSlug('contact', $contactEmail);
    }

    public function featuresHub(): array
    {
        $content = config('marketing_features_hub_content', []);

        return is_array($content) ? $this->interpolator->interpolate($content) : [];
    }

    public function navigation(): array
    {
        return collect(MarketingStaticPageDefinition::slugs())
            ->map(function (string $slug): ?array {
                $content = config("marketing_static_content.{$slug}");

                if (! is_array($content) || ! isset($content['nav_label'])) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'path' => MarketingStaticPageDefinition::path($slug),
                    'nav_label' => (string) $content['nav_label'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function homePlanMeta(): array
    {
        $home = config('marketing_home_content', []);

        if (! is_array($home)) {
            return ['plan_taglines' => [], 'feature_labels' => [], 'plan_limits' => []];
        }

        $interpolated = $this->interpolator->interpolate($home);

        return [
            'plan_taglines' => $interpolated['plan_taglines'] ?? [],
            'feature_labels' => $interpolated['feature_labels'] ?? [],
            'plan_limits' => $interpolated['plan_limits'] ?? [],
            'pricing_section' => $interpolated['pricing_section'] ?? [],
        ];
    }
}
