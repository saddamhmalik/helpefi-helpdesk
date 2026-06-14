<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\MarketingBlogPost;
use App\Domains\Platform\Repositories\MarketingBlogPostRepository;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MarketingBlogPostService
{
    public function __construct(
        private MarketingBlogPostRepository $posts,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function listForAdmin(): array
    {
        return $this->posts->allForAdmin()
            ->map(fn (MarketingBlogPost $post) => $this->presentAdmin($post))
            ->all();
    }

    public function findForAdmin(int $id): array
    {
        return $this->presentAdmin($this->posts->find($id));
    }

    public function slugOptions(?int $ignoreId = null): array
    {
        return $this->posts->allForAdmin()
            ->filter(fn (MarketingBlogPost $post) => $post->id !== $ignoreId)
            ->map(fn (MarketingBlogPost $post) => [
                'slug' => $post->slug,
                'title' => $post->title,
            ])
            ->values()
            ->all();
    }

    public function create(array $data): array
    {
        $payload = $this->buildPayload($data);
        $payload['created_by'] = Auth::guard('platform')->id();
        $post = $this->posts->create($payload);

        $this->audit->record('platform.blog_post.created', $post);

        CentralMarketingPresenter::forgetCache();

        return $this->presentAdmin($post);
    }

    public function update(int $id, array $data): array
    {
        $post = $this->posts->find($id);
        $before = $this->presentAdmin($post);
        $post = $this->posts->update($post, $this->buildPayload($data, $post));

        $this->audit->recordChanges('platform.blog_post.updated', $post, $before, $this->presentAdmin($post));

        CentralMarketingPresenter::forgetCache();

        return $this->presentAdmin($post);
    }

    public function delete(int $id): void
    {
        $post = $this->posts->find($id);

        $this->posts->delete($post);

        $this->audit->record('platform.blog_post.deleted', null, [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
        ]);

        CentralMarketingPresenter::forgetCache();
    }

    public function presentPublic(MarketingBlogPost $post): array
    {
        return [
            'slug' => $post->slug,
            'seo_key' => $this->seoKey($post->slug),
            'path' => $this->path($post->slug),
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'body_paragraphs' => $this->bodyParagraphs($post->body),
            'published_at' => $post->published_at?->toDateString(),
            'updated_at' => $post->updated_at?->toDateString(),
            'reading_minutes' => $post->reading_minutes,
            'og_image' => $post->og_image_url,
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'related' => $post->related_slugs ?? [],
        ];
    }

    public function presentPublicIndex(MarketingBlogPost $post): array
    {
        return [
            'slug' => $post->slug,
            'path' => $this->path($post->slug),
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'reading_minutes' => $post->reading_minutes,
        ];
    }

    public function seoKey(string $slug): string
    {
        return 'blog_'.str_replace('-', '_', $slug);
    }

    public function path(string $slug): string
    {
        return '/blog/'.$slug;
    }

    public function validationRules(?MarketingBlogPost $existing = null): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    if ($this->posts->slugExists((string) $value, $existing?->id)) {
                        $fail('This slug is already in use.');
                    }
                },
            ],
            'excerpt' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'status' => ['required', 'string', 'in:'.MarketingBlogPost::STATUS_DRAFT.','.MarketingBlogPost::STATUS_PUBLISHED],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'og_image_url' => ['nullable', 'string', 'max:500', 'url', 'starts_with:https://'],
            'related_slugs' => ['nullable', 'array'],
            'related_slugs.*' => ['string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    private function buildPayload(array $data, ?MarketingBlogPost $existing = null): array
    {
        $body = trim((string) ($data['body'] ?? ''));
        $status = (string) ($data['status'] ?? MarketingBlogPost::STATUS_DRAFT);
        $publishedAt = $data['published_at'] ?? null;

        if ($status === MarketingBlogPost::STATUS_PUBLISHED && empty($publishedAt)) {
            $publishedAt = now();
        }

        if ($status === MarketingBlogPost::STATUS_DRAFT) {
            $publishedAt = null;
        }

        $related = collect($data['related_slugs'] ?? [])
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->unique()
            ->reject(fn (string $slug) => $slug === ($data['slug'] ?? $existing?->slug))
            ->values()
            ->all();

        return [
            'slug' => Str::slug((string) ($data['slug'] ?? '')),
            'title' => $this->sanitizeText($data['title'] ?? '', 200),
            'excerpt' => $this->sanitizeText($data['excerpt'] ?? '', 500),
            'body' => strip_tags($body),
            'status' => $status,
            'published_at' => $publishedAt,
            'reading_minutes' => $this->estimateReadingMinutes($body),
            'related_slugs' => $related === [] ? null : $related,
            'og_image_url' => filled($data['og_image_url'] ?? null) ? $data['og_image_url'] : null,
            'seo_title' => filled($data['seo_title'] ?? null) ? $this->sanitizeText($data['seo_title'], 200) : null,
            'seo_description' => filled($data['seo_description'] ?? null) ? $this->sanitizeText($data['seo_description'], 320) : null,
        ];
    }

    private function presentAdmin(MarketingBlogPost $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'status' => $post->status,
            'published_at' => $post->published_at?->format('Y-m-d'),
            'reading_minutes' => $post->reading_minutes,
            'related_slugs' => $post->related_slugs ?? [],
            'og_image_url' => $post->og_image_url,
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'public_path' => $post->isPublished() ? $this->path($post->slug) : null,
            'creator' => $post->creator ? [
                'id' => $post->creator->id,
                'name' => $post->creator->name,
            ] : null,
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }

    private function sanitizeText(mixed $value, int $maxLength): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_substr(trim($text), 0, $maxLength);
    }

    private function bodyParagraphs(string $body): array
    {
        return collect(preg_split("/\r\n|\r|\n\s*\n/", trim($body)) ?: [])
            ->map(fn (string $paragraph) => trim($paragraph))
            ->filter()
            ->values()
            ->all();
    }

    private function estimateReadingMinutes(string $body): int
    {
        $words = str_word_count(strip_tags($body));

        return max(1, (int) ceil($words / 200));
    }
}
