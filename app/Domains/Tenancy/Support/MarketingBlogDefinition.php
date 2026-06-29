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

    public static function forIndexPaginated(
        ?string $search,
        ?string $categorySlug,
        array $tagSlugs,
        int $perPage = 10
    ): array {
        $paginator = app(MarketingBlogPostRepository::class)->paginatePublishedForMarketing(
            $perPage,
            $search,
            $categorySlug,
            $tagSlugs
        );

        $posts = $paginator->getCollection()
            ->map(fn (MarketingBlogPost $post) => app(MarketingBlogPostService::class)->presentPublicIndex($post))
            ->values()
            ->all();

        $available = self::filtersForIndex();

        return [
            'posts' => $posts,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
                'tags' => $tagSlugs,
            ],
            'availableCategories' => $available['categories'],
            'availableTags' => $available['tags'],
        ];
    }

    public static function filtersForIndex(): array
    {
        $rows = app(MarketingBlogPostRepository::class)->allPublishedForFilters();

        $categorySlugs = [];
        $tagSlugs = [];

        foreach ($rows as $row) {
            $cats = $row->category_slugs ?? [];
            $tags = $row->tag_slugs ?? [];

            if (is_array($cats)) {
                foreach ($cats as $c) {
                    if (is_string($c) && trim($c) !== '') {
                        $categorySlugs[] = $c;
                    }
                }
            }

            if (is_array($tags)) {
                foreach ($tags as $t) {
                    if (is_string($t) && trim($t) !== '') {
                        $tagSlugs[] = $t;
                    }
                }
            }
        }

        $categorySlugs = array_values(array_unique($categorySlugs));
        $tagSlugs = array_values(array_unique($tagSlugs));

        sort($categorySlugs);
        sort($tagSlugs);

        return [
            'categories' => collect($categorySlugs)
                ->map(fn (string $slug) => [
                    'slug' => $slug,
                    'name' => self::labelFromSlug($slug),
                ])
                ->values()
                ->all(),
            'tags' => collect($tagSlugs)
                ->map(fn (string $slug) => [
                    'slug' => $slug,
                    'name' => self::labelFromSlug($slug),
                ])
                ->values()
                ->all(),
        ];
    }

    public static function recommended(string $slug, int $limit = 3): array
    {
        $post = self::find($slug);

        if ($post === null) {
            return [];
        }

        $currentCategorySlugs = collect($post['categories'] ?? [])
            ->map(fn (array $c) => (string) ($c['slug'] ?? ''))
            ->filter()
            ->values()
            ->all();

        $currentTagSlugs = collect($post['tags'] ?? [])
            ->map(fn (array $t) => (string) ($t['slug'] ?? ''))
            ->filter()
            ->values()
            ->all();

        $excludeSlugs = array_values(array_unique(array_merge(
            [$slug],
            collect($post['related'] ?? [])->map(fn (string $s) => (string) $s)->filter()->values()->all()
        )));

        $relatedSlugs = collect($post['related'] ?? [])
            ->filter(fn (string $s) => is_string($s) && $s !== '' && $s !== $slug)
            ->unique()
            ->values()
            ->all();

        $postsBySlug = app(MarketingBlogPostRepository::class)
            ->publishedBySlugs($relatedSlugs)
            ->keyBy('slug');

        $recommended = collect($relatedSlugs)
            ->map(fn (string $relatedSlug) => $postsBySlug->get($relatedSlug))
            ->filter()
            ->map(fn (MarketingBlogPost $p) => app(MarketingBlogPostService::class)->presentPublic($p))
            ->values()
            ->all();

        if (count($recommended) >= $limit) {
            return array_slice($recommended, 0, $limit);
        }

        $candidates = app(MarketingBlogPostRepository::class)->recentPublishedExcluding($excludeSlugs, 30);

        $scored = $candidates->map(function (MarketingBlogPost $candidate) use ($currentCategorySlugs, $currentTagSlugs, $slug) {
            $candidateCategorySlugs = is_array($candidate->category_slugs) ? $candidate->category_slugs : [];
            $candidateTagSlugs = is_array($candidate->tag_slugs) ? $candidate->tag_slugs : [];

            $categoryOverlap = array_intersect($currentCategorySlugs, $candidateCategorySlugs);
            $tagOverlap = array_intersect($currentTagSlugs, $candidateTagSlugs);

            return [
                'post' => $candidate,
                'score' => count($categoryOverlap) * 2 + count($tagOverlap),
            ];
        })->sortByDesc('score')
            ->map(fn (array $row) => $row['post'])
            ->values()
            ->all();

        foreach ($scored as $candidate) {
            if (count($recommended) >= $limit) {
                break;
            }

            $recommended[] = app(MarketingBlogPostService::class)->presentPublic($candidate);
        }

        return array_slice($recommended, 0, $limit);
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

    private static function labelFromSlug(string $slug): string
    {
        $raw = str_replace(['-', '_'], ' ', trim($slug));
        $raw = preg_replace('/\s+/u', ' ', $raw) ?? $raw;

        $first = mb_substr($raw, 0, 1);
        $rest = mb_substr($raw, 1);

        return mb_strtoupper($first).$rest;
    }
}
