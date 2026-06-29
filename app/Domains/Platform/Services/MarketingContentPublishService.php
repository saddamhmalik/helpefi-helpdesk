<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\MarketingContentDraft;
use App\Domains\Platform\Repositories\MarketingContentDraftRepository;
use App\Domains\Platform\Repositories\MarketingPageContentRepository;
use App\Domains\Platform\Repositories\MarketingSeoMetadataRepository;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Platform\Support\PlatformAuditRecorder;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\IntegrationLandingDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingContentPublishService
{
    public function __construct(
        private MarketingContentDraftRepository $drafts,
        private MarketingPageContentRepository $pages,
        private MarketingSeoMetadataRepository $seoMeta,
        private MarketingBlogPostService $blogPosts,
        private MarketingPageContentService $pageContent,
        private PlatformAuditRecorder $audit,
    ) {
    }

    public function publish(int $draftId): array
    {
        $draft = $this->drafts->find($draftId);

        if ($draft->status === MarketingContentDraft::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['draft' => 'This draft is already published.']);
        }

        if (collect($draft->duplicate_warnings ?? [])->contains(fn (array $w) => ($w['severity'] ?? '') === 'block')) {
            throw ValidationException::withMessages(['draft' => 'Resolve duplicate content warnings before publishing.']);
        }

        $content = $draft->effectiveContent();

        if ($content === []) {
            throw ValidationException::withMessages(['draft' => 'No content to publish.']);
        }

        $reference = match ($draft->content_type) {
            MarketingContentType::BLOG_OUTLINE => $this->publishBlogOutline($draft, $content),
            default => $this->publishPageContent($draft, $content),
        };

        $this->publishSeoMetadata($draft);

        $updated = $this->drafts->update($draft, [
            'status' => MarketingContentDraft::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_reference' => $reference,
            'updated_by' => Auth::guard('platform')->id(),
        ]);

        $this->audit->record('platform.marketing_content.published', $updated, $reference);
        $this->pageContent->forgetCache();

        return [
            'draft' => $updated->id,
            'reference' => $reference,
        ];
    }

    private function publishPageContent(MarketingContentDraft $draft, array $content): array
    {
        $pageType = $this->normalizePageType($draft->content_type);
        $slug = $draft->slug ?? Str::slug($draft->title);

        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => 'Slug is required to publish page content.']);
        }

        $pageKey = $draft->target_page_key ?? $this->buildPageKey($pageType, $slug);

        $row = $this->pages->upsert($pageType, $slug, [
            'content' => $content,
            'internal_links' => $draft->internal_links,
            'page_key' => $pageKey,
            'status' => 'published',
            'source_draft_id' => $draft->id,
            'published_at' => now(),
            'updated_by' => Auth::guard('platform')->id(),
        ]);

        return [
            'type' => 'page_content',
            'page_type' => $pageType,
            'slug' => $slug,
            'page_key' => $pageKey,
            'page_content_id' => $row->id,
            'path' => $this->resolvePath($pageType, $slug),
        ];
    }

    private function publishBlogOutline(MarketingContentDraft $draft, array $content): array
    {
        $slug = (string) ($content['slug'] ?? $draft->slug ?? Str::slug($draft->title));
        $body = $this->outlineToBody($content['outline'] ?? []);
        $excerpt = trim((string) ($content['excerpt'] ?? mb_substr((string) $draft->brief, 0, 300)));

        if ($excerpt === '') {
            $excerpt = mb_substr((string) $draft->title, 0, 200);
        }

        if ($body === '') {
            $body = (string) $draft->brief;
        }

        $post = $this->blogPosts->create([
            'title' => (string) ($content['title'] ?? $draft->title),
            'slug' => $slug,
            'excerpt' => $excerpt,
            'body' => $body,
            'status' => 'draft',
            'seo_title' => $draft->seo['seo_title'] ?? null,
            'seo_description' => $draft->seo['meta_description'] ?? null,
            'category_slugs' => $content['suggested_categories'] ?? [],
            'tag_slugs' => $content['suggested_tags'] ?? [],
        ]);

        return [
            'type' => 'blog_post',
            'blog_post_id' => $post['id'] ?? null,
            'slug' => $slug,
            'path' => '/admin/blog/'.$post['id'].'/edit',
        ];
    }

    private function publishSeoMetadata(MarketingContentDraft $draft): void
    {
        $pageKey = $draft->target_page_key;

        if (! $pageKey || ! is_array($draft->seo)) {
            return;
        }

        $this->seoMeta->upsertByPageKey($pageKey, [
            'ai_seo_title' => $draft->seo['seo_title'] ?? null,
            'ai_meta_description' => $draft->seo['meta_description'] ?? null,
            'ai_keywords' => $draft->seo['keywords'] ?? null,
            'ai_og_description' => $draft->seo['meta_description'] ?? null,
            'ai_twitter_description' => $draft->seo['meta_description'] ?? null,
            'source_content' => json_encode($draft->effectiveContent()),
            'ai_source' => $draft->ai_source,
            'ai_generated_at' => $draft->generated_at,
            'updated_by' => Auth::guard('platform')->id(),
        ]);
    }

    private function outlineToBody(array $outline): string
    {
        $sections = [];

        foreach ($outline as $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? ''));
            $bullets = is_array($section['bullets'] ?? null) ? $section['bullets'] : [];

            if ($heading !== '') {
                $sections[] = $heading;
            }

            foreach ($bullets as $bullet) {
                if (is_string($bullet) && trim($bullet) !== '') {
                    $sections[] = '- '.$bullet;
                }
            }

            $sections[] = '';
        }

        return trim(implode("\n", $sections));
    }

    private function normalizePageType(string $contentType): string
    {
        return $contentType === MarketingContentType::LANDING
            ? MarketingContentType::FEATURE
            : $contentType;
    }

    private function buildPageKey(string $pageType, string $slug): string
    {
        $prefix = MarketingContentType::seoKeyPrefix($pageType) ?? 'page_';

        return $prefix.str_replace('-', '_', $slug);
    }

    private function resolvePath(string $pageType, string $slug): string
    {
        return match ($pageType) {
            MarketingContentType::FEATURE => MarketingFeatureDefinition::path($slug),
            MarketingContentType::VERTICAL => VerticalLandingDefinition::path($slug),
            MarketingContentType::COMPARISON => CompareLandingDefinition::path($slug),
            MarketingContentType::INTEGRATION => IntegrationLandingDefinition::path($slug),
            default => '/'.$slug,
        };
    }
}
