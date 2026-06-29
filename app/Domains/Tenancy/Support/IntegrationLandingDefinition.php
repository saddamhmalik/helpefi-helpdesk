<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;

class IntegrationLandingDefinition
{
    public static function slugs(): array
    {
        return array_keys(config('marketing_integrations', []));
    }

    public static function find(string $slug): ?array
    {
        $config = config("marketing_integrations.{$slug}");

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
            ->map(fn (array $integration) => [
                'slug' => $integration['slug'],
                'path' => $integration['path'],
            ])
            ->all();
    }

    public static function path(string $slug): string
    {
        return '/integrations/'.$slug;
    }

    public static function seoKey(string $slug): string
    {
        return 'integration_'.str_replace('-', '_', $slug);
    }

    public static function slugFromSeoKey(string $seoKey): ?string
    {
        if (! str_starts_with($seoKey, 'integration_')) {
            return null;
        }

        $slug = str_replace('_', '-', substr($seoKey, strlen('integration_')));

        if (self::find($slug) !== null) {
            return $slug;
        }

        return app(MarketingPageContentService::class)->isKnownSlug(MarketingContentType::INTEGRATION, $slug)
            ? $slug
            : null;
    }

    public static function isValid(string $slug): bool
    {
        if (self::find($slug) !== null) {
            return true;
        }

        return app(MarketingPageContentService::class)->isKnownSlug(MarketingContentType::INTEGRATION, $slug);
    }
}
