<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\MarketingBlogPost;
use Illuminate\Database\Eloquent\Collection;

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
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    public function publishedSlugs(): array
    {
        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
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
            ->first();
    }

    public function publishedBySlugs(array $slugs): Collection
    {
        if ($slugs === []) {
            return new Collection();
        }

        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->whereIn('slug', $slugs)
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
