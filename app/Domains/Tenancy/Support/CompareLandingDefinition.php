<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;

class CompareLandingDefinition
{
    public static function slugs(): array
    {
        return array_keys(config('marketing_comparisons', []));
    }

    public static function find(string $slug): ?array
    {
        $config = config("marketing_comparisons.{$slug}");

        if (! is_array($config)) {
            return null;
        }

        return array_merge($config, [
            'slug' => $slug,
            'seo_key' => self::seoKey($slug),
            'path' => self::path($slug),
        ]);
    }

    public static function all(): array
    {
        return collect(self::slugs())
            ->map(fn (string $slug) => self::find($slug))
            ->filter()
            ->values()
            ->all();
    }

    public static function forNavigation(): array
    {
        return collect(self::all())
            ->map(fn (array $compare) => [
                'slug' => $compare['slug'],
                'path' => $compare['path'],
            ])
            ->all();
    }

    public static function path(string $slug): string
    {
        return '/compare/'.$slug.'-vs-helpefi';
    }

    public static function slugFromComparison(string $comparison): ?string
    {
        $suffix = '-vs-helpefi';

        if (str_ends_with($comparison, $suffix)) {
            $slug = substr($comparison, 0, -strlen($suffix));

            return self::isValid($slug) ? $slug : null;
        }

        return self::isValid($comparison) ? $comparison : null;
    }

    public static function seoKey(string $slug): string
    {
        return 'compare_'.str_replace('-', '_', $slug);
    }

    public static function slugFromSeoKey(string $seoKey): ?string
    {
        if (! str_starts_with($seoKey, 'compare_')) {
            return null;
        }

        $slug = str_replace('_', '-', substr($seoKey, strlen('compare_')));

        if (self::find($slug) !== null) {
            return $slug;
        }

        return app(MarketingPageContentService::class)->isKnownSlug(MarketingContentType::COMPARISON, $slug)
            ? $slug
            : null;
    }

    public static function isValid(string $slug): bool
    {
        if (self::find($slug) !== null) {
            return true;
        }

        return app(MarketingPageContentService::class)->isKnownSlug(MarketingContentType::COMPARISON, $slug);
    }
}
