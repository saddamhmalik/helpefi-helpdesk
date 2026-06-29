<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\MarketingBlogPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MarketingBlogPostRepository
{
    public function allForAdmin(): Collection
    {
        return MarketingBlogPost::query()
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function published(): Collection
    {
        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereNull('published_at');
            })
            ->with('creator:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    public function publishedSlugs(): array
    {
        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereNull('published_at');
            })
            ->orderByDesc('published_at')
            ->pluck('slug')
            ->all();
    }

    public function find(int $id): MarketingBlogPost
    {
        return MarketingBlogPost::query()->findOrFail($id);
    }

    public function findPublishedBySlug(string $slug): ?MarketingBlogPost
    {
        return MarketingBlogPost::query()
            ->where('slug', $slug)
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereNull('published_at');
            })
            ->with('creator:id,name')
            ->first();
    }

    public function publishedBySlugs(array $slugs): Collection
    {
        if ($slugs === []) {
            return new Collection();
        }

        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereNull('published_at');
            })
            ->with('creator:id,name')
            ->whereIn('slug', $slugs)
            ->get();
    }

    public function paginatePublishedForMarketing(
        int $perPage,
        ?string $search,
        ?string $categorySlug,
        array $tagSlugs
    ): LengthAwarePaginator {
        $query = MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereNull('published_at');
            });

        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('body', 'like', $like);
            });
        }

        $categorySlug = is_string($categorySlug) ? trim($categorySlug) : '';
        if ($categorySlug !== '') {
            $query->whereJsonContains('category_slugs', $categorySlug);
        }

        $tagSlugs = array_values(array_filter(array_map(
            fn ($t) => is_string($t) ? trim($t) : '',
            $tagSlugs
        )));

        if ($tagSlugs !== []) {
            $query->where(function ($q) use ($tagSlugs) {
                foreach ($tagSlugs as $tag) {
                    $q->orWhereJsonContains('tag_slugs', $tag);
                }
            });
        }

        return $query
            ->with('creator:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function allPublishedForFilters(): Collection
    {
        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereNull('published_at');
            })
            ->select(['id', 'category_slugs', 'tag_slugs'])
            ->get();
    }

    public function recentPublishedExcluding(array $excludeSlugs, int $limit): Collection
    {
        $excludeSlugs = array_values(array_filter(array_map(
            fn ($s) => is_string($s) ? trim($s) : '',
            $excludeSlugs
        )));

        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereNull('published_at');
            })
            ->when($excludeSlugs !== [], fn ($q) => $q->whereNotIn('slug', $excludeSlugs))
            ->with('creator:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function findBySlug(string $slug): ?MarketingBlogPost
    {
        return MarketingBlogPost::query()->where('slug', $slug)->first();
    }

    public function create(array $data): MarketingBlogPost
    {
        return MarketingBlogPost::query()->create($data);
    }

    public function update(MarketingBlogPost $post, array $data): MarketingBlogPost
    {
        $post->update($data);

        return $post->fresh();
    }

    public function delete(MarketingBlogPost $post): void
    {
        $post->delete();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return MarketingBlogPost::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
