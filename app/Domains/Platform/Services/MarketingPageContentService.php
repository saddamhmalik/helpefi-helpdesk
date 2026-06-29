<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\MarketingPageContentRepository;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\IntegrationLandingDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use App\Domains\Tenancy\Support\CentralMarketingPresenter;
use App\Domains\Tenancy\Support\MarketingContentInterpolator;
use Illuminate\Support\Facades\Schema;

class MarketingPageContentService
{
    public function __construct(
        private MarketingPageContentRepository $pages,
        private MarketingContentInterpolator $interpolator,
    ) {
    }

    public function resolve(string $pageType, string $configKey, string $slug): ?array
    {
        $override = $this->pages->findPublished($pageType, $slug);
        $content = $override?->content ?? config("{$configKey}.{$slug}");

        if (! is_array($content)) {
            return null;
        }

        return $this->interpolator->interpolate($content);
    }

    public function internalLinksFor(string $pageType, string $slug): array
    {
        $override = $this->pages->findPublished($pageType, $slug);

        return $override?->internal_links ?? [];
    }

    public function slugsForType(string $pageType): array
    {
        $pageType = $this->normalizePageType($pageType);

        return array_values(array_unique([
            ...$this->registrySlugs($pageType),
            ...$this->publishedSlugsSafe($pageType),
        ]));
    }

    public function indexableSlugsForType(string $pageType): array
    {
        $configKey = MarketingContentType::configKey($pageType);

        if ($configKey === null) {
            return [];
        }

        return collect($this->slugsForType($pageType))
            ->filter(fn (string $slug) => $this->resolve($pageType, $configKey, $slug) !== null)
            ->values()
            ->all();
    }

    public function pathFor(string $pageType, string $slug): string
    {
        $pageType = $this->normalizePageType($pageType);

        return match ($pageType) {
            MarketingContentType::FEATURE => MarketingFeatureDefinition::path($slug),
            MarketingContentType::VERTICAL => VerticalLandingDefinition::path($slug),
            MarketingContentType::COMPARISON => CompareLandingDefinition::path($slug),
            MarketingContentType::INTEGRATION => IntegrationLandingDefinition::path($slug),
            default => '/'.$slug,
        };
    }

    public function seoKeyFor(string $pageType, string $slug): string
    {
        $pageType = $this->normalizePageType($pageType);

        return match ($pageType) {
            MarketingContentType::FEATURE => MarketingFeatureDefinition::seoKey($slug),
            MarketingContentType::VERTICAL => VerticalLandingDefinition::seoKey($slug),
            MarketingContentType::COMPARISON => CompareLandingDefinition::seoKey($slug),
            MarketingContentType::INTEGRATION => IntegrationLandingDefinition::seoKey($slug),
            default => 'page_'.str_replace('-', '_', $slug),
        };
    }

    public function sitemapLastmodFor(string $pageType, string $slug): ?string
    {
        $row = $this->pages->findPublished($this->normalizePageType($pageType), $slug);

        if ($row === null) {
            return null;
        }

        $timestamp = $row->updated_at ?? $row->published_at;

        return $timestamp?->toAtomString();
    }

    public function resolveSlugFromSeoKey(string $seoKey): ?array
    {
        foreach (MarketingContentType::pageTypes() as $pageType) {
            $prefix = MarketingContentType::seoKeyPrefix($pageType);

            if ($prefix === null || ! str_starts_with($seoKey, $prefix)) {
                continue;
            }

            $slug = str_replace('_', '-', substr($seoKey, strlen($prefix)));

            if ($slug === '') {
                continue;
            }

            if ($this->isKnownSlug($pageType, $slug)) {
                return [
                    'type' => $this->normalizePageType($pageType),
                    'slug' => $slug,
                ];
            }
        }

        return null;
    }

    public function isValidSlug(string $pageType, string $slug): bool
    {
        return $this->isKnownSlug($pageType, $slug)
            && $this->resolve(
                $this->normalizePageType($pageType),
                (string) MarketingContentType::configKey($pageType),
                $slug,
            ) !== null;
    }

    public function isKnownSlug(string $pageType, string $slug): bool
    {
        $pageType = $this->normalizePageType($pageType);

        if ($this->pages->findPublished($pageType, $slug)) {
            return true;
        }

        return match ($pageType) {
            MarketingContentType::FEATURE => MarketingFeatureDefinition::find($slug) !== null,
            MarketingContentType::VERTICAL => VerticalLandingDefinition::find($slug) !== null,
            MarketingContentType::COMPARISON => CompareLandingDefinition::find($slug) !== null,
            MarketingContentType::INTEGRATION => IntegrationLandingDefinition::find($slug) !== null,
            default => false,
        };
    }

    public function publishedSlugs(string $pageType): array
    {
        return $this->pages->publishedSlugs($this->normalizePageType($pageType));
    }

    public function forgetCache(): void
    {
        CentralMarketingPresenter::forgetCache();
    }

    private function normalizePageType(string $pageType): string
    {
        return $pageType === MarketingContentType::LANDING
            ? MarketingContentType::FEATURE
            : $pageType;
    }

    private function registrySlugs(string $pageType): array
    {
        return match ($pageType) {
            MarketingContentType::FEATURE => MarketingFeatureDefinition::slugs(),
            MarketingContentType::VERTICAL => VerticalLandingDefinition::slugs(),
            MarketingContentType::COMPARISON => CompareLandingDefinition::slugs(),
            MarketingContentType::INTEGRATION => IntegrationLandingDefinition::slugs(),
            default => [],
        };
    }

    private function publishedSlugsSafe(string $pageType): array
    {
        try {
            if (! Schema::hasTable('marketing_page_content')) {
                return [];
            }

            return $this->pages->publishedSlugs($pageType);
        } catch (\Throwable) {
            return [];
        }
    }
}
