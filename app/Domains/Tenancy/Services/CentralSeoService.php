<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Platform\Services\MarketingPageContentService;
use App\Domains\Platform\Support\MarketingContentType;
use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\IntegrationLandingDefinition;
use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use App\Domains\Tenancy\Support\MigrateLandingDefinition;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use App\Domains\Platform\Services\MarketingSeoMetadataService;
use Illuminate\Support\Facades\App;

class CentralSeoService
{
    private const CORE_PATHS = [
        'home' => '/',
        'register' => '/register',
        'login' => '/login',
        'blog' => '/blog',
        'features_index' => '/features',
        'compare_index' => '/compare',
        'migrate_index' => '/migrate',
    ];

    private array $stringCache = [];

    private array $centralCache = [];

    public function __construct(
        private MarketingJsonLd $jsonLd,
        private MarketingSeoMetadataService $seoMeta,
        private MarketingPageContentService $pageContent,
    )
    {
    }

    public function shared(): array
    {
        return [
            'siteUrl' => $this->siteUrl(),
            'siteName' => config('app.name', 'Helpefi'),
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
        $overrides = $this->seoMeta->resolveForPageKey($page);

        if (isset($overrides['title'])) {
            $title = (string) $overrides['title'];
        }

        if (isset($overrides['description'])) {
            $description = (string) $overrides['description'];
        }

        $title = $this->clampMetaTitle($title);
        $description = $this->clampMetaDescription($description);

        $canonical = $this->canonicalForPage($page);
        $ogImage = $this->ogImageUrl($page);

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $overrides['keywords'] ?? null,
            'ogDescription' => $overrides['og_description'] ?? null,
            'twitterDescription' => $overrides['twitter_description'] ?? null,
            'robots' => $this->robotsForPage($page),
            'canonical' => $canonical,
            'brand' => $brand,
            'ogImage' => $ogImage,
            'preloads' => $ogImage ? [[
                'href' => $ogImage,
                'as' => 'image',
                'fetchpriority' => 'high',
            ]] : [],
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

    public function ogImageUrl(?string $page = null): ?string
    {
        if (is_string($page) && $page !== '') {
            $blogSlug = MarketingBlogDefinition::slugFromSeoKey($page);

            if ($blogSlug !== null) {
                $post = MarketingBlogDefinition::find($blogSlug);

                if ($post !== null && filled($post['og_image'] ?? null)) {
                    return (string) $post['og_image'];
                }
            }

            $pageImage = config("marketing_seo.og_images.{$page}");

            if (is_string($pageImage) && $pageImage !== '') {
                return str_starts_with($pageImage, 'http')
                    ? $pageImage
                    : $this->siteUrl().'/'.ltrim($pageImage, '/');
            }
        }

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
        $defaultLastmod = $this->defaultSitemapLastmod();
        $entries = [
            $this->sitemapEntry(route('central.home'), 'weekly', '1.0', $defaultLastmod),
            $this->sitemapEntry(route('central.static.pricing'), 'weekly', '0.95', $defaultLastmod),
            $this->sitemapEntry(route('central.blog.index'), 'weekly', '0.8', $defaultLastmod),
            $this->sitemapEntry(route('central.features.index'), 'weekly', '0.9', $defaultLastmod),
            $this->sitemapEntry(route('central.compare.index'), 'weekly', '0.85', $defaultLastmod),
            $this->sitemapEntry(route('central.migrate.index'), 'monthly', '0.85', $defaultLastmod),
            $this->sitemapEntry(route('central.static.integrations'), 'monthly', '0.8', $defaultLastmod),
            $this->sitemapEntry(route('central.static.industries'), 'monthly', '0.8', $defaultLastmod),
            $this->sitemapEntry(route('central.static.resources'), 'weekly', '0.75', $defaultLastmod),
            $this->sitemapEntry(route('central.static.contact'), 'monthly', '0.7', $defaultLastmod),
            $this->sitemapEntry(route('central.static.support'), 'monthly', '0.7', $defaultLastmod),
        ];

        foreach (MarketingStaticPageDefinition::all() as $page) {
            if (($page['sitemap'] ?? true) === false) {
                continue;
            }

            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$page['path'],
                $page['changefreq'] ?? 'monthly',
                $page['priority'] ?? '0.7',
                $defaultLastmod,
            );
        }

        foreach (MarketingContentType::pageTypes() as $pageType) {
            $configKey = MarketingContentType::configKey($pageType);

            if ($configKey === null) {
                continue;
            }

            [$changefreq, $priority] = match ($pageType) {
                MarketingContentType::FEATURE => ['monthly', '0.9'],
                MarketingContentType::VERTICAL => ['monthly', '0.9'],
                MarketingContentType::COMPARISON => ['monthly', '0.85'],
                MarketingContentType::INTEGRATION => ['monthly', '0.85'],
                default => ['monthly', '0.8'],
            };

            foreach ($this->pageContent->indexableSlugsForType($pageType) as $slug) {
                $entries[] = $this->sitemapEntry(
                    $this->siteUrl().$this->pageContent->pathFor($pageType, $slug),
                    $changefreq,
                    $priority,
                    $this->pageContent->sitemapLastmodFor($pageType, $slug) ?? $defaultLastmod,
                );
            }
        }

        foreach (MigrateLandingDefinition::all() as $migration) {
            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$migration['path'],
                'monthly',
                '0.85',
                $defaultLastmod,
            );
        }

        foreach (MarketingBlogDefinition::all() as $post) {
            $entries[] = $this->sitemapEntry(
                $this->siteUrl().$post['path'],
                'monthly',
                '0.75',
                $post['updated_at'] ?? $post['published_at'] ?? null,
            );
        }

        $imageMap = collect($this->imageSitemapEntries())->keyBy('loc');

        return collect($entries)
            ->unique('loc')
            ->map(function (array $entry) use ($imageMap): array {
                $images = $imageMap->get($entry['loc'])['images'] ?? null;

                if (is_array($images) && $images !== []) {
                    $entry['images'] = $images;
                }

                return $entry;
            })
            ->values()
            ->all();
    }

    public function robotsLines(): array
    {
        if (App::environment('staging')) {
            return [
                'User-agent: *',
                'Disallow: /',
            ];
        }

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

    public function imageSitemapEntries(): array
    {
        $defaultLastmod = $this->defaultSitemapLastmod();

        $entries = [];

        $push = function (string $loc, ?string $imageUrl, ?string $lastmod = null) use (&$entries, $defaultLastmod): void {
            $img = is_string($imageUrl) && $imageUrl !== '' ? $imageUrl : null;
            if (! $img) {
                return;
            }

            $entries[] = [
                'loc' => $loc,
                'lastmod' => $lastmod ? date('c', strtotime($lastmod)) : $defaultLastmod,
                'images' => [
                    ['loc' => $img],
                ],
            ];
        };

        $push(route('central.home'), $this->ogImageUrl('home'), $defaultLastmod);
        $push(route('central.features.index'), $this->ogImageUrl('features_index'), $defaultLastmod);
        $push(route('central.compare.index'), $this->ogImageUrl('compare_index'), $defaultLastmod);
        $push(route('central.migrate.index'), $this->ogImageUrl('migrate_index'), $defaultLastmod);
        $push(route('central.blog.index'), $this->ogImageUrl('blog'), $defaultLastmod);

        foreach (MarketingStaticPageDefinition::all() as $page) {
            if (($page['sitemap'] ?? true) === false) {
                continue;
            }

            $seoKey = isset($page['seo_key']) ? (string) $page['seo_key'] : null;

            $path = (string) ($page['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $push(
                $this->siteUrl().$path,
                $this->ogImageUrl($seoKey ?: null),
                $defaultLastmod,
            );
        }

        foreach (MarketingContentType::pageTypes() as $pageType) {
            foreach ($this->pageContent->indexableSlugsForType($pageType) as $slug) {
                $path = $this->pageContent->pathFor($pageType, $slug);

                if ($path === '') {
                    continue;
                }

                $seoKey = $this->pageContent->seoKeyFor($pageType, $slug);
                $push(
                    $this->siteUrl().$path,
                    $this->ogImageUrl($seoKey),
                    $this->pageContent->sitemapLastmodFor($pageType, $slug) ?? $defaultLastmod,
                );
            }
        }

        foreach (MigrateLandingDefinition::all() as $migration) {
            $path = (string) ($migration['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $seoKey = MigrateLandingDefinition::seoKey((string) ($migration['slug'] ?? '')) ?? null;
            $push($this->siteUrl().$path, $this->ogImageUrl($seoKey), $defaultLastmod);
        }

        foreach (MarketingBlogDefinition::all() as $post) {
            $path = (string) ($post['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $image = $post['og_image'] ?? $this->ogImageUrl();
            $push(
                $this->siteUrl().$path,
                is_string($image) ? $image : null,
                $post['updated_at'] ?? $post['published_at'] ?? null,
            );
        }

        return collect($entries)->unique('loc')->values()->all();
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

    private function defaultSitemapLastmod(): string
    {
        $paths = [
            config_path('marketing_seo.php'),
            config_path('marketing_static_content.php'),
            resource_path('js/locales/en/central.json'),
        ];

        $timestamps = collect($paths)
            ->filter(fn (string $path) => is_file($path))
            ->map(fn (string $path) => (int) filemtime($path))
            ->filter(fn (int $time) => $time > 0)
            ->all();

        $max = $timestamps === [] ? time() : max($timestamps);

        return date('c', $max);
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

        $resolved = $this->pageContent->resolveSlugFromSeoKey($page);

        if ($resolved !== null) {
            return $this->siteUrl().$this->pageContent->pathFor($resolved['type'], $resolved['slug']);
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

        $integrationSlug = IntegrationLandingDefinition::slugFromSeoKey($page);

        if ($integrationSlug !== null) {
            return $this->siteUrl().IntegrationLandingDefinition::path($integrationSlug);
        }

        $migrateSlug = MigrateLandingDefinition::slugFromSeoKey($page);

        if ($migrateSlug !== null) {
            return $this->siteUrl().MigrateLandingDefinition::path($migrateSlug);
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

            $faqNode = $this->jsonLd->faqPage($this->homeFaqs($trialDays, $brand));

            if ($faqNode !== null) {
                $graph[] = $faqNode;
            }

            return $this->jsonLd->encode($graph);
        }

        if (MarketingBlogDefinition::slugFromSeoKey($page) !== null) {
            $slug = MarketingBlogDefinition::slugFromSeoKey($page);
            $post = MarketingBlogDefinition::find($slug);
            $image = $post['og_image'] ?? $this->ogImageUrl();
            $authorName = $post['author']['name'] ?? null;
            $categories = collect($post['categories'] ?? [])
                ->map(fn ($c) => is_array($c) ? ($c['name'] ?? null) : null)
                ->filter()
                ->values()
                ->all();
            $tags = collect($post['tags'] ?? [])
                ->map(fn ($t) => is_array($t) ? ($t['name'] ?? null) : null)
                ->filter()
                ->values()
                ->all();

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
                $authorName,
                $categories,
                $tags,
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

        $faqNode = $this->pageFaqs($page, $trialDays, $brand);

        if ($faqNode !== null) {
            $graph[] = $faqNode;
        }

        return $this->jsonLd->encode($graph);
    }

    private function homeFaqs(int $trialDays, string $brand): array
    {
        $faqs = $this->centralSection('home.faqs');

        if (! is_array($faqs)) {
            return [];
        }

        return collect($faqs)
            ->map(fn (array $faq) => [
                'q' => $this->interpolate((string) ($faq['q'] ?? ''), $this->marketingReplacements($brand, $trialDays)),
                'a' => $this->interpolate((string) ($faq['a'] ?? ''), $this->marketingReplacements($brand, $trialDays)),
            ])
            ->filter(fn (array $faq) => $faq['q'] !== '' && $faq['a'] !== '')
            ->values()
            ->all();
    }

    private function pageFaqs(string $page, int $trialDays, string $brand): ?array
    {
        $migrateSlug = MigrateLandingDefinition::slugFromSeoKey($page);

        if ($migrateSlug !== null) {
            return $this->faqNodeFromSection("migrations.{$migrateSlug}.faq", $brand, $trialDays);
        }

        $slug = VerticalLandingDefinition::slugFromSeoKey($page)
            ?? MarketingFeatureDefinition::slugFromSeoKey($page);

        if ($slug === null) {
            return null;
        }

        $prefix = VerticalLandingDefinition::slugFromSeoKey($page) !== null
            ? "verticals.{$slug}.faq"
            : "feature_pages.{$slug}.faq";

        return $this->faqNodeFromSection($prefix, $brand, $trialDays);
    }

    private function faqNodeFromSection(string $prefix, string $brand, int $trialDays): ?array
    {
        $faqs = $this->centralSection($prefix);

        if (! is_array($faqs) || $faqs === []) {
            return null;
        }

        $replacements = $this->marketingReplacements($brand, $trialDays);

        $items = collect($faqs)
            ->map(fn ($faq) => is_array($faq) ? [
                'q' => $this->interpolate((string) ($faq['q'] ?? $faq['question'] ?? ''), $replacements),
                'a' => $this->interpolate((string) ($faq['a'] ?? $faq['answer'] ?? ''), $replacements),
            ] : null)
            ->filter(fn (?array $faq) => is_array($faq) && $faq['q'] !== '' && $faq['a'] !== '')
            ->values()
            ->all();

        return $this->jsonLd->faqPage($items);
    }

    private function marketingReplacements(string $brand, int $trialDays): array
    {
        return [
            'brand' => $brand,
            'days' => (string) $trialDays,
            'trialDays' => (string) $trialDays,
        ];
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

    private function clampMetaTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        if ($title === '') {
            return $title;
        }

        if (mb_strlen($title) >= 30) {
            return $title;
        }

        return $title.' — AI Helpdesk Software';
    }

    private function clampMetaDescription(string $description): string
    {
        $description = trim(preg_replace('/\s+/u', ' ', $description) ?? '');

        if ($description === '') {
            return $description;
        }

        $length = mb_strlen($description);

        if ($length > 160) {
            $cut = mb_substr($description, 0, 157);
            $lastSpace = mb_strrpos($cut, ' ');

            if ($lastSpace !== false && $lastSpace > 100) {
                $cut = mb_substr($cut, 0, $lastSpace);
            }

            return rtrim($cut, '.,;:-').'…';
        }

        if ($length < 120) {
            $suffix = ' Start a free trial on Helpefi — no credit card required.';

            if ($length + mb_strlen($suffix) <= 160) {
                return $description.$suffix;
            }
        }

        return $description;
    }
}
