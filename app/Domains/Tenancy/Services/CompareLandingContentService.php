<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\CompareLandingDefinition;

class CompareLandingContentService
{
    public function forSlug(string $slug): ?array
    {
        $content = config("marketing_comparison_content.{$slug}");

        if (! is_array($content)) {
            return null;
        }

        return $this->interpolate($content);
    }

    public function navigation(): array
    {
        return collect(CompareLandingDefinition::slugs())
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

    private function interpolate(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->interpolate($item), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return str_replace('{brand}', config('app.name', 'helpefi'), $value);
    }
}
