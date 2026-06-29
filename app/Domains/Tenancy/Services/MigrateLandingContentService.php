<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\MarketingContentInterpolator;
use App\Domains\Tenancy\Support\MigrateLandingDefinition;

class MigrateLandingContentService
{
    public function __construct(private MarketingContentInterpolator $interpolator)
    {
    }

    public function forSlug(string $slug): ?array
    {
        $content = config("marketing_migration_content.{$slug}");

        if (! is_array($content)) {
            return null;
        }

        return $this->interpolator->interpolate($content);
    }

    public function navigation(): array
    {
        return collect(MigrateLandingDefinition::slugs())
            ->map(function (string $slug) {
                $content = $this->forSlug($slug);

                if ($content === null) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'path' => MigrateLandingDefinition::path($slug),
                    'nav_label' => (string) ($content['nav_label'] ?? $content['source_name'] ?? $slug),
                    'source_name' => (string) ($content['source_name'] ?? $slug),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function hub(): array
    {
        $content = config('marketing_migrations_hub_content', []);

        return is_array($content) ? $this->interpolator->interpolate($content) : [];
    }
}
