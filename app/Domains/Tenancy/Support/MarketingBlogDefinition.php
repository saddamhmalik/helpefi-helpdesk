<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Platform\Models\MarketingBlogPost;
use App\Domains\Platform\Repositories\MarketingBlogPostRepository;
use App\Domains\Platform\Services\MarketingBlogPostService;

class MarketingBlogDefinition
{
    public static function slugs(): array
    {
        return app(MarketingBlogPostRepository::class)->publishedSlugs();
    }

    public static function find(string $slug): ?array
    {
        $post = app(MarketingBlogPostRepository::class)->findPublishedBySlug($slug);

        if ($post === null) {
            return null;
        }

        return app(MarketingBlogPostService::class)->presentPublic($post);
    }

    public static function all(): array
    {
        return app(MarketingBlogPostRepository::class)->published()
            ->map(fn (MarketingBlogPost $post) => app(MarketingBlogPostService::class)->presentPublic($post))
            ->values()
            ->all();
    }

    public static function forIndex(): array
    {
        return app(MarketingBlogPostRepository::class)->published()
            ->map(fn (MarketingBlogPost $post) => app(MarketingBlogPostService::class)->presentPublicIndex($post))
            ->values()
            ->all();
    }

    public static function path(string $slug): string
    {
        return app(MarketingBlogPostService::class)->path($slug);
    }

    public static function seoKey(string $slug): string
    {
        return app(MarketingBlogPostService::class)->seoKey($slug);
    }

    public static function slugFromSeoKey(string $seoKey): ?string
    {
        if (! str_starts_with($seoKey, 'blog_')) {
            return null;
        }

        $slug = str_replace('_', '-', substr($seoKey, strlen('blog_')));

        return self::find($slug) ? $slug : null;
    }

    public static function isValid(string $slug): bool
    {
        return self::find($slug) !== null;
    }

    public static function related(string $slug): array
    {
        $post = self::find($slug);

        if ($post === null) {
            return [];
        }

        $slugs = collect($post['related'] ?? [])
            ->filter(fn (string $relatedSlug) => $relatedSlug !== '' && $relatedSlug !== $slug)
            ->unique()
            ->values()
            ->all();

        if ($slugs === []) {
            return [];
        }

        $postsBySlug = app(MarketingBlogPostRepository::class)
            ->publishedBySlugs($slugs)
            ->keyBy('slug');

        return collect($slugs)
            ->map(fn (string $relatedSlug) => $postsBySlug->get($relatedSlug))
            ->filter()
            ->map(fn (MarketingBlogPost $relatedPost) => app(MarketingBlogPostService::class)->presentPublic($relatedPost))
            ->values()
            ->all();
    }
}
