<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\MarketingBlogPost;
use App\Domains\Platform\Repositories\MarketingContentDraftRepository;
use App\Domains\Platform\Repositories\MarketingPageContentRepository;
use App\Domains\Platform\Support\MarketingContentType;

class MarketingContentCorpusService
{
    public function __construct(
        private MarketingContentDraftRepository $drafts,
        private MarketingPageContentRepository $pages,
    ) {
    }

    public function allEntries(): array
    {
        return array_merge(
            $this->configCorpus(),
            $this->blogCorpus(),
            $this->drafts->textCorpus(),
            $this->pages->textCorpus(),
        );
    }

    public function existingSlugs(string $contentType): array
    {
        $registryKey = MarketingContentType::registryKey($contentType);
        $configSlugs = $registryKey ? array_keys(config($registryKey, [])) : [];
        $pageType = in_array($contentType, MarketingContentType::pageTypes(), true) ? $contentType : null;
        $dbSlugs = $pageType ? $this->pages->publishedSlugs($pageType) : [];

        return array_values(array_unique(array_merge($configSlugs, $dbSlugs)));
    }

    public function internalLinkTargets(): array
    {
        $links = [];

        foreach (MarketingContentType::pageTypes() as $type) {
            $configKey = MarketingContentType::configKey($type);
            $registryKey = MarketingContentType::registryKey($type);

            if (! $configKey || ! $registryKey) {
                continue;
            }

            foreach (array_keys(config($registryKey, [])) as $slug) {
                $content = config("{$configKey}.{$slug}");
                if (! is_array($content)) {
                    continue;
                }

                $links[] = [
                    'type' => $type,
                    'slug' => $slug,
                    'label' => (string) ($content['nav_label'] ?? $content['competitor_name'] ?? $slug),
                ];
            }
        }

        foreach ($this->pages->allPublished() as $row) {
            $links[] = [
                'type' => $row->page_type,
                'slug' => $row->slug,
                'label' => (string) ($row->content['nav_label'] ?? $row->content['competitor_name'] ?? $row->slug),
            ];
        }

        $links[] = ['type' => 'static', 'slug' => 'pricing', 'label' => 'Pricing', 'path' => '/pricing'];
        $links[] = ['type' => 'static', 'slug' => 'blog', 'label' => 'Blog', 'path' => '/blog'];

        MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->limit(30)
            ->get(['slug', 'title'])
            ->each(function (MarketingBlogPost $post) use (&$links) {
                $links[] = [
                    'type' => 'blog',
                    'slug' => $post->slug,
                    'label' => $post->title,
                    'path' => '/blog/'.$post->slug,
                ];
            });

        return collect($links)
            ->unique(fn (array $item) => ($item['type'] ?? '').':'.($item['slug'] ?? ''))
            ->values()
            ->all();
    }

    private function configCorpus(): array
    {
        $entries = [];

        foreach ([
            'marketing_feature_content',
            'marketing_vertical_content',
            'marketing_comparison_content',
            'marketing_integration_content',
            'marketing_migration_content',
            'marketing_static_content',
            'marketing_home_content',
        ] as $configKey) {
            $pages = config($configKey, []);

            if (! is_array($pages)) {
                continue;
            }

            foreach ($pages as $slug => $content) {
                if (! is_array($content)) {
                    continue;
                }

                $entries[] = [
                    'id' => 'config:'.$configKey.':'.$slug,
                    'title' => (string) ($content['nav_label'] ?? $content['competitor_name'] ?? $slug),
                    'text' => $this->flattenContent($content),
                ];
            }
        }

        return $entries;
    }

    private function blogCorpus(): array
    {
        return MarketingBlogPost::query()
            ->where('status', MarketingBlogPost::STATUS_PUBLISHED)
            ->get(['id', 'slug', 'title', 'excerpt', 'body'])
            ->map(fn (MarketingBlogPost $post) => [
                'id' => 'blog:'.$post->id,
                'title' => $post->title,
                'text' => trim($post->title.' '.$post->excerpt.' '.$post->body),
            ])
            ->all();
    }

    private function flattenContent(array $content): string
    {
        $parts = [];

        array_walk_recursive($content, function ($value) use (&$parts) {
            if (is_string($value) && trim($value) !== '') {
                $parts[] = $value;
            }
        });

        return implode(' ', $parts);
    }
}
