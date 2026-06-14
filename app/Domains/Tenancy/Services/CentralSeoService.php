<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use Illuminate\Support\Facades\App;

class CentralSeoService
{
    private const CORE_PATHS = [
        'home' => '/',
        'register' => '/register',
        'login' => '/login',
        'blog' => '/blog',
    ];

    private array $stringCache = [];

    private array $centralCache = [];

    public function __construct(private MarketingJsonLd $jsonLd)
    {
    }

    public function shared(): array
    {
        return [
            'siteUrl' => $this->siteUrl(),
            'siteName' => config('app.name', 'helpefi'),
            'ogImage' => $this->ogImageUrl(),
            'locales' => ['en'],
        ];
    }

    public function meta(
        string $page,
        string $brand,
        int $trialDays,
        string $currency = 'USD',
        array $socialUrls = [],
    ): array {
        $strings = $this->seoStrings();
        $replacements = ['brand' => $brand, 'days' => (string) $trialDays];

        [$title, $description] = $this->resolvePageMeta($page, $brand, $strings, $replacements);
        $canonical = $this->canonicalForPage($page);

        return [
            'title' => $title,
            'description' => $description,
            'robots' => $this->robotsForPage($page),
            'canonical' => $canonical,
            'brand' => $brand,
            'ogImage' => $this->ogImageUrl(),
            'ogLocale' => $this->ogLocale(),
            'ogLocaleAlternates' => [],
            'twitterSite' => config('marketing_seo.twitter.site'),
            'jsonLd' => $this->jsonLdForPage(
                $page,
                $brand,
                $title,
                $description,
                $trialDays,
                $strings,
                $currency,
                $canonical,
                $socialUrls,
            ),
        ];
    }

    public function siteUrl(): string
    {
        $configured = config('marketing_seo.site_url');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public function ogLocale(): string
    {
        return 'en_US';
    }

    public function ogImageUrl(): ?string
    {
        $configured = config('tenancy.central_og_image');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return file_exists(public_path('og-image.png'))
            ? $this->siteUrl().'/og-image.png'
            : null;
    }

    public function sitemapEntries(): array
    {
        $entries = [
            $this->sitemapEntry(route('central.home'), 'weekly', '1.0'),
            $this->sitemapEntry(route('central.register'), 'monthly', '0.8'),
        ];

        foreach (MarketingStaticPageDefinition::all() as $page) {
            if (($page['sitemap'] ?? true) === false) {
                continue;
            }

            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$page['path'],
                $page['changefreq'] ?? 'monthly',
                $page['priority'] ?? '0.7',
            );
        }

        foreach (MarketingFeatureDefinition::all() as $feature) {
            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$feature['path'],
                'monthly',
                '0.9',
            );
        }

        foreach (VerticalLandingDefinition::all() as $vertical) {
            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$vertical['path'],
                'monthly',
                '0.9',
            );
        }

        foreach (CompareLandingDefinition::all() as $compare) {
            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$compare['path'],
                'monthly',
                '0.85',
            );
        }

        $entries[] = $this->sitemapEntry(route('central.blog.index'), 'weekly', '0.8');

        foreach (MarketingBlogDefinition::all() as $post) {
            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$post['path'],
                'monthly',
                '0.75',
                $post['updated_at'] ?? $post['published_at'] ?? null,
            );
        }

        return $entries;
    }

    public function robotsLines(): array
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
        ];

        foreach (config('marketing_seo.robots.disallow', []) as $path) {
            $lines[] = 'Disallow: '.rtrim($path, '/');
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$this->siteUrl().'/sitemap.xml';

        return $lines;
    }

    private function sitemapEntry(string $loc, string $changefreq, string $priority, ?string $lastmod = null): array
    {
        return [
            'loc' => $loc,
            'changefreq' => $changefreq,
            'priority' => $priority,
            'lastmod' => $lastmod ? date('c', strtotime($lastmod)) : now()->toAtomString(),
        ];
    }

    private function robotsForPage(string $page): string
    {
        return match ($page) {
            'login' => 'noindex, follow',
            default => 'index, follow',
        };
    }

    private function canonicalForPage(string $page): string
    {
        if (isset(self::CORE_PATHS[$page])) {
            return $this->siteUrl().self::CORE_PATHS[$page];
        }

        $featureSlug = MarketingFeatureDefinition::slugFromSeoKey($page);

        if ($featureSlug !== null) {
            return $this->siteUrl().MarketingFeatureDefinition::path($featureSlug);
        }

        $staticSlug = MarketingStaticPageDefinition::slugFromSeoKey($page);

        if ($staticSlug !== null) {
            return $this->siteUrl().MarketingStaticPageDefinition::path($staticSlug);
        }

        $blogSlug = MarketingBlogDefinition::slugFromSeoKey($page);

        if ($blogSlug !== null) {
            return $this->siteUrl().MarketingBlogDefinition::path($blogSlug);
        }

        $verticalSlug = VerticalLandingDefinition::slugFromSeoKey($page);

        if ($verticalSlug !== null) {
            return $this->siteUrl().VerticalLandingDefinition::path($verticalSlug);
        }

        $compareSlug = CompareLandingDefinition::slugFromSeoKey($page);

        if ($compareSlug !== null) {
            return $this->siteUrl().CompareLandingDefinition::path($compareSlug);
        }

        return $this->siteUrl().'/';
    }

    private function jsonLdForPage(
        string $page,
        string $brand,
        string $title,
        string $description,
        int $trialDays,
        array $strings,
        string $currency,
        string $canonical,
        array $socialUrls,
    ): ?string {
        $baseUrl = $this->siteUrl();
        $email = config('marketing_seo.organization.contact_email');
        $graph = [
            $this->jsonLd->organization($brand, $baseUrl, $socialUrls, is_string($email) ? $email : null),
            $this->jsonLd->website($brand, $baseUrl),
        ];

        if ($page === 'home') {
            $graph[] = $this->jsonLd->softwareApplication(
                $brand,
                $description,
                $baseUrl,
                $currency,
                $this->interpolate($strings['trial_offer'] ?? '', ['days' => (string) $trialDays]),
            );

            $faqNode = $this->jsonLd->faqPage($this->homeFaqs($trialDays));

            if ($faqNode !== null) {
                $graph[] = $faqNode;
            }

            return $this->jsonLd->encode($graph);
        }

        if (MarketingBlogDefinition::slugFromSeoKey($page) !== null) {
            $slug = MarketingBlogDefinition::slugFromSeoKey($page);
            $post = MarketingBlogDefinition::find($slug);
            $image = $post['og_image'] ?? $this->ogImageUrl();

            $graph[] = $this->jsonLd->webPage($title, $description, $canonical, $baseUrl);
            $graph[] = $this->jsonLd->article(
                $title,
                $description,
                $canonical,
                $baseUrl,
                $brand,
                (string) ($post['published_at'] ?? now()->toDateString()),
                isset($post['updated_at']) ? (string) $post['updated_at'] : null,
                is_string($image) ? $image : null,
            );
            $graph[] = $this->jsonLd->breadcrumbList([
                ['name' => $brand, 'url' => $baseUrl],
                ['name' => 'Blog', 'url' => $baseUrl.'/blog'],
                ['name' => $title, 'url' => $canonical],
            ]);

            return $this->jsonLd->encode($graph);
        }

        if ($page === 'blog') {
            $graph[] = $this->jsonLd->webPage($title, $description, $canonical, $baseUrl);
            $graph[] = $this->jsonLd->breadcrumbList([
                ['name' => $brand, 'url' => $baseUrl],
                ['name' => 'Blog', 'url' => $canonical],
            ]);

            return $this->jsonLd->encode($graph);
        }

        $graph[] = $this->jsonLd->webPage($title, $description, $canonical, $baseUrl);
        $graph[] = $this->jsonLd->breadcrumbList([
            ['name' => $brand, 'url' => $baseUrl],
            ['name' => $title, 'url' => $canonical],
        ]);

        $faqNode = $this->pageFaqs($page, $trialDays);

        if ($faqNode !== null) {
            $graph[] = $faqNode;
        }

        return $this->jsonLd->encode($graph);
    }

    private function homeFaqs(int $trialDays): array
    {
        $faqs = $this->centralSection('home.faqs');

        if (! is_array($faqs)) {
            return [];
        }

        return collect($faqs)
            ->map(fn (array $faq) => [
                'q' => $this->interpolate((string) ($faq['q'] ?? ''), ['trialDays' => (string) $trialDays]),
                'a' => $this->interpolate((string) ($faq['a'] ?? ''), ['trialDays' => (string) $trialDays]),
            ])
            ->filter(fn (array $faq) => $faq['q'] !== '' && $faq['a'] !== '')
            ->values()
            ->all();
    }

    private function pageFaqs(string $page, int $trialDays): ?array
    {
        $slug = VerticalLandingDefinition::slugFromSeoKey($page)
            ?? MarketingFeatureDefinition::slugFromSeoKey($page);

        if ($slug === null) {
            return null;
        }

        $prefix = VerticalLandingDefinition::slugFromSeoKey($page) !== null
            ? "verticals.{$slug}.faq"
            : "feature_pages.{$slug}.faq";

        $faqs = $this->centralSection($prefix);

        if (! is_array($faqs) || $faqs === []) {
            return null;
        }

        $items = collect($faqs)
            ->map(fn ($faq) => is_array($faq) ? [
                'q' => $this->interpolate((string) ($faq['q'] ?? $faq['question'] ?? ''), ['trialDays' => (string) $trialDays, 'days' => (string) $trialDays]),
                'a' => $this->interpolate((string) ($faq['a'] ?? $faq['answer'] ?? ''), ['trialDays' => (string) $trialDays, 'days' => (string) $trialDays]),
            ] : null)
            ->filter(fn (?array $faq) => is_array($faq) && $faq['q'] !== '' && $faq['a'] !== '')
            ->values()
            ->all();

        return $this->jsonLd->faqPage($items);
    }

    private function centralSection(string $path): mixed
    {
        $central = $this->centralJson();

        return data_get($central, 'central.'.$path);
    }

    private function centralJson(): array
    {
        if ($this->centralCache !== []) {
            return $this->centralCache;
        }

        $path = resource_path('js/locales/en/central.json');

        if (! is_file($path)) {
            return $this->centralCache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->centralCache = is_array($decoded) ? $decoded : [];
    }

    private function seoStrings(): array
    {
        $strings = $this->loadSeoStrings('en');

        return $strings === [] ? [] : $strings;
    }

    private function loadSeoStrings(string $locale): array
    {
        if (array_key_exists($locale, $this->stringCache)) {
            return $this->stringCache[$locale];
        }

        $path = resource_path("js/locales/{$locale}/central.json");

        if (! is_file($path)) {
            return $this->stringCache[$locale] = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return $this->stringCache[$locale] = [];
        }

        $seo = $decoded['central']['seo'] ?? [];

        return $this->stringCache[$locale] = is_array($seo) ? $seo : [];
    }

    private function interpolate(string $value, array $replacements): string
    {
        foreach ($replacements as $key => $replacement) {
            $value = str_replace('{'.$key.'}', (string) $replacement, $value);
        }

        return $value;
    }

    private function resolvePageMeta(string $page, string $brand, array $strings, array $replacements): array
    {
        $blogSlug = MarketingBlogDefinition::slugFromSeoKey($page);

        if ($blogSlug !== null) {
            $post = MarketingBlogDefinition::find($blogSlug);

            if ($post !== null) {
                $title = filled($post['seo_title'] ?? null)
                    ? $this->interpolate((string) $post['seo_title'], $replacements).' · '.$brand
                    : $post['title'].' · '.$brand;
                $description = filled($post['seo_description'] ?? null)
                    ? (string) $post['seo_description']
                    : (string) ($post['excerpt'] ?? '');

                return [$title, $description];
            }
        }

        return [
            $this->interpolate($strings["{$page}_title"] ?? $brand, $replacements),
            $this->interpolate($strings["{$page}_description"] ?? '', $replacements),
        ];
    }
}
