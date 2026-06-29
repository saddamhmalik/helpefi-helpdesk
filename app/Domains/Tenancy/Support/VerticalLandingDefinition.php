<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;

class VerticalLandingDefinition
{
    public static function slugs(): array
    {
        return array_keys(config('marketing_verticals', []));
    }

    public static function find(string $slug): ?array
    {
        $config = config("marketing_verticals.{$slug}");

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
            ->map(fn (array $vertical) => [
                'slug' => $vertical['slug'],
                'path' => $vertical['path'],
            ])
            ->all();
    }

    public static function path(string $slug): string
    {
        return '/helpdesk-for-'.$slug;
    }

    public static function slugFromPath(string $path): ?string
    {
        $prefix = '/helpdesk-for-';

        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $slug = substr($path, strlen($prefix));

        return self::isValid($slug) ? $slug : null;
    }

    public static function seoKey(string $slug): string
    {
        return 'vertical_'.str_replace('-', '_', $slug);
    }

    public static function slugFromSeoKey(string $seoKey): ?string
    {
        if (! str_starts_with($seoKey, 'vertical_')) {
            return null;
        }

        $slug = str_replace('_', '-', substr($seoKey, strlen('vertical_')));

        if (self::find($slug) !== null) {
            return $slug;
        }

        return app(MarketingPageContentService::class)->isKnownSlug(MarketingContentType::VERTICAL, $slug)
            ? $slug
            : null;
    }

    public static function isValid(string $slug): bool
    {
        if (self::find($slug) !== null) {
            return true;
        }

        return app(MarketingPageContentService::class)->isKnownSlug(MarketingContentType::VERTICAL, $slug);
    }
}
