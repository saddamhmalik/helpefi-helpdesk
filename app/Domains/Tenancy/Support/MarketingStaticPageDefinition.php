<?php

namespace App\Domains\Tenancy\Support;

class MarketingStaticPageDefinition
{
    public static function slugs(): array
    {
        return array_keys(config('marketing_seo.static_pages', []));
    }

    public static function find(string $slug): ?array
    {
        $config = config("marketing_seo.static_pages.{$slug}");

        if (! is_array($config)) {
            return null;
        }

        return array_merge($config, [
            'slug' => $slug,
            'seo_key' => self::seoKey($slug),
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

    public static function path(string $slug): string
    {
        return (string) (self::find($slug)['path'] ?? '/');
    }

    public static function seoKey(string $slug): string
    {
        return 'static_'.str_replace('-', '_', $slug);
    }

    public static function slugFromSeoKey(string $seoKey): ?string
    {
        if (! str_starts_with($seoKey, 'static_')) {
            return null;
        }

        $slug = str_replace('_', '-', substr($seoKey, strlen('static_')));

        return self::find($slug) ? $slug : null;
    }

    public static function isValid(string $slug): bool
    {
        return self::find($slug) !== null;
    }
}
