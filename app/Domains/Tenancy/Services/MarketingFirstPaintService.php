<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\IntegrationLandingDefinition;
use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\MarketingSeoContext;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use App\Domains\Tenancy\Support\MigrateLandingDefinition;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use Illuminate\Http\Request;

class MarketingFirstPaintService
{
    public function __construct(
        private MarketingHomeContentService $homeContent,
        private MarketingChromeContentService $chromeContent,
        private CentralSettingsService $settings,
        private FeatureLandingContentService $features,
        private VerticalLandingContentService $verticals,
        private CompareLandingContentService $comparisons,
        private IntegrationLandingContentService $integrations,
        private MigrateLandingContentService $migrations,
        private MarketingStaticContentService $staticContent,
    ) {
    }

    public function shellFor(Request $request): ?array
    {
        if (! MarketingSeoContext::isMarketingRequest($request)) {
            return null;
        }

        if ($request->routeIs('central.login', 'central.register')) {
            return null;
        }

        $page = $this->pageFor($request);

        if ($page === null) {
            return null;
        }

        $home = $this->homeContent->content();
        $chrome = $this->chromeContent->content();
        $layout = $chrome['layout'] ?? [];

        return [
            'trialDays' => $this->settings->trialDays(),
            'promoTrial' => $home['promo_trial'] ?? '',
            'promoStart' => $home['promo_start'] ?? '',
            'layout' => $layout,
            'page' => $page,
            'nav' => $this->navigation(),
        ];
    }

    private function pageFor(Request $request): ?array
    {
        return match (true) {
            $request->routeIs('central.home') => $this->homePage(),
            $request->routeIs('central.feature') => $this->featurePage((string) $request->route('feature')),
            $request->routeIs('central.vertical') => $this->verticalPage((string) $request->route('vertical')),
            $request->routeIs('central.compare') => $this->comparePage((string) $request->route('comparison')),
            $request->routeIs('central.integration') => $this->integrationPage((string) $request->route('integration')),
            $request->routeIs('central.migrate') => $this->migratePage((string) $request->route('source')),
            $request->routeIs('central.features.index') => $this->featuresHubPage(),
            $request->routeIs('central.compare.index') => $this->compareHubPage(),
            $request->routeIs('central.migrate.index') => $this->migrateHubPage(),
            $request->routeIs('central.static.integrations') => $this->integrationsHubPage(),
            $request->routeIs('central.static.industries') => $this->industriesHubPage(),
            $request->routeIs('central.blog.index') => $this->blogIndexPage(),
            $request->routeIs('central.blog.show') => $this->blogPostPage((string) $request->route('slug')),
            $request->routeIs(
                'central.static.pricing',
                'central.static.about',
                'central.static.resources',
                'central.static.contact',
                'central.static.support',
                'central.static.privacy',
                'central.static.terms',
            ) => $this->staticPage($this->staticSlugFromRoute($request)),
            default => null,
        };
    }

    private function staticSlugFromRoute(Request $request): string
    {
        return match ($request->route()?->getName()) {
            'central.static.pricing' => 'pricing',
            'central.static.about' => 'about',
            'central.static.resources' => 'resources',
            'central.static.contact' => 'contact',
            'central.static.support' => 'support',
            'central.static.privacy' => 'privacy',
            'central.static.terms' => 'terms',
            default => 'pricing',
        };
    }

    private function homePage(): array
    {
        $home = $this->homeContent->content();

        $sections = [];
        $intro = trim((string) ($home['intro'] ?? ''));
        if ($intro !== '') {
            $sections[] = ['title' => 'Overview', 'body' => $intro];
        }

        $switch = $home['switch_section'] ?? [];
        $switchBody = trim(implode(' ', array_filter([
            (string) ($switch['subtitle'] ?? ''),
            (string) ($switch['old_way_body'] ?? ''),
            (string) ($switch['new_way_body'] ?? ''),
        ])));
        if ($switchBody !== '') {
            $sections[] = [
                'title' => (string) ($switch['title'] ?? 'Why teams switch'),
                'body' => $switchBody,
            ];
        }

        foreach ($home['pain_points'] ?? [] as $point) {
            if (! is_array($point)) {
                continue;
            }

            $pain = (string) ($point['pain'] ?? '');
            $gain = (string) ($point['gain'] ?? '');
            if ($pain !== '' || $gain !== '') {
                $sections[] = ['title' => $pain, 'body' => $gain];
            }
        }

        $aiSection = $home['ai_section'] ?? [];
        $aiSubtitle = trim((string) ($aiSection['subtitle'] ?? ''));
        if ($aiSubtitle !== '') {
            $sections[] = [
                'title' => trim(((string) ($aiSection['title_line1'] ?? '')).' '.((string) ($aiSection['title_line2'] ?? ''))),
                'body' => $aiSubtitle,
            ];
        }

        foreach ($home['ai_capabilities'] ?? [] as $capability) {
            if (! is_array($capability)) {
                continue;
            }

            $title = (string) ($capability['title'] ?? '');
            $body = (string) ($capability['body'] ?? '');
            if ($title !== '' || $body !== '') {
                $sections[] = ['title' => $title, 'body' => $body];
            }
        }

        foreach ($home['category_highlights'] ?? [] as $category => $items) {
            if (! is_array($items)) {
                continue;
            }

            $hint = (string) ($home['category_hints'][$category] ?? $category);
            $body = trim(implode(' ', array_map('strval', $items)));
            if ($body !== '') {
                $sections[] = ['title' => $hint, 'body' => $body];
            }
        }

        foreach ($home['steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $title = (string) ($step['title'] ?? '');
            $body = trim(((string) ($step['body'] ?? '')).' '.((string) ($step['detail'] ?? '')));
            if ($title !== '' || $body !== '') {
                $sections[] = ['title' => $title, 'body' => $body];
            }
        }

        $product = $home['product_section'] ?? [];
        $productBody = trim(((string) ($product['subtitle'] ?? '')));
        if ($productBody !== '') {
            $sections[] = [
                'title' => (string) ($product['title'] ?? 'Platform overview'),
                'body' => $productBody,
            ];
        }

        foreach ($home['comparisons'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $feature = trim((string) ($row['feature'] ?? ''));
            if ($feature === '') {
                continue;
            }

            $us = $row['us'] ?? '';
            $them = $row['them'] ?? '';
            $usText = is_bool($us) ? ($us ? 'Included' : 'Not included') : (string) $us;
            $themText = is_bool($them) ? ($them ? 'Included' : 'Not included') : (string) $them;

            $sections[] = [
                'title' => 'Platform comparison: '.$feature,
                'body' => "Helpefi: {$usText}. Typical stacked tools: {$themText}.",
            ];
        }

        $compare = $home['compare_section'] ?? [];
        $compareBody = trim((string) ($compare['subtitle'] ?? ''));
        if ($compareBody !== '') {
            $sections[] = [
                'title' => (string) ($compare['title'] ?? 'Compare'),
                'body' => $compareBody,
            ];
        }

        foreach ($home['bento_bodies'] ?? [] as $key => $body) {
            $body = trim((string) $body);
            if ($body !== '') {
                $sections[] = [
                    'title' => ucwords(str_replace('_', ' ', (string) $key)),
                    'body' => $body,
                ];
            }
        }

        $featuresSection = $home['features_section'] ?? [];
        $featuresBody = trim((string) ($featuresSection['full_platform_body'] ?? ''));
        if ($featuresBody !== '') {
            $sections[] = [
                'title' => (string) ($featuresSection['full_platform_title'] ?? 'Platform features'),
                'body' => $featuresBody,
            ];
        }

        $pricingSection = $home['pricing_section'] ?? [];
        $pricingBody = trim(((string) ($pricingSection['subtitle'] ?? '')));
        if ($pricingBody !== '') {
            $sections[] = [
                'title' => (string) ($pricingSection['title'] ?? 'Pricing'),
                'body' => $pricingBody,
            ];
        }

        foreach ($home['deep_dives'] ?? [] as $dive) {
            if (! is_array($dive)) {
                continue;
            }

            $title = (string) ($dive['title'] ?? '');
            $body = (string) ($dive['body'] ?? '');
            if ($title !== '' || $body !== '') {
                $sections[] = ['title' => $title, 'body' => $body];
            }
        }

        $conclusion = trim((string) ($home['conclusion']['body'] ?? ''));
        if ($conclusion !== '') {
            $sections[] = [
                'title' => (string) ($home['conclusion']['title'] ?? 'Get started'),
                'body' => $conclusion,
            ];
        }

        $faqs = [];
        foreach ($home['faqs'] ?? [] as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $q = (string) ($faq['q'] ?? '');
            $a = (string) ($faq['a'] ?? '');
            if ($q !== '' && $a !== '') {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
        }

        return [
            'type' => 'home',
            'h1' => $home['hero_title_line1'] ?? '',
            'h1Highlight' => $home['hero_title_line2'] ?? '',
            'subtitle' => $home['hero_subtitle'] ?? '',
            'ctaPrimary' => $home['hero_cta_long'] ?? '',
            'ctaSecondary' => $home['hero_try_ai'] ?? '#ai',
            'ctaSecondaryHref' => '#ai',
            'sections' => $sections,
            'faqs' => $faqs,
        ];
    }

    private function landingPageFromContent(array $content, string $type, array $breadcrumbs = []): array
    {
        $sections = [];
        $items = $content['features'] ?? $content['reasons'] ?? $content['pains'] ?? $content['sections'] ?? [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = (string) ($item['title'] ?? '');
            $body = (string) ($item['body'] ?? '');

            if ($title !== '' || $body !== '') {
                $sections[] = ['title' => $title, 'body' => $body];
            }
        }

        $faqs = [];
        foreach ($content['faq'] ?? [] as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $q = (string) ($faq['q'] ?? '');
            $a = (string) ($faq['a'] ?? '');

            if ($q !== '' && $a !== '') {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
        }

        return [
            'type' => $type,
            'badge' => (string) ($content['badge'] ?? ''),
            'h1' => (string) ($content['hero_title'] ?? ''),
            'h1Highlight' => (string) ($content['hero_highlight'] ?? ''),
            'subtitle' => (string) ($content['hero_subtitle'] ?? ''),
            'sections' => $sections,
            'faqs' => $faqs,
            'ctaTitle' => (string) ($content['cta_title'] ?? ''),
            'ctaBody' => (string) ($content['cta_body'] ?? ''),
            'breadcrumbs' => $breadcrumbs,
            'updatedAt' => (string) ($content['updated_at'] ?? ''),
            'author' => $this->eeatPerson($content['author'] ?? null),
            'reviewer' => $this->eeatPerson($content['reviewer'] ?? null),
            'externalReferences' => $this->eeatReferences($content['external_references'] ?? []),
        ];
    }

    private function eeatPerson(?array $person): ?array
    {
        if (! is_array($person) || empty($person['name'] ?? '')) {
            return null;
        }

        return [
            'name' => (string) $person['name'],
            'role' => (string) ($person['role'] ?? ''),
        ];
    }

    private function eeatReferences(array $references): array
    {
        return collect($references)
            ->filter(fn ($ref) => is_array($ref) && ! empty($ref['url'] ?? ''))
            ->map(fn ($ref) => [
                'url' => (string) $ref['url'],
                'title' => (string) ($ref['title'] ?? ''),
                'description' => (string) ($ref['description'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function featurePage(string $slug): ?array
    {
        $content = $this->features->forSlug($slug);

        if ($content === null) {
            return null;
        }

        $page = $this->landingPageFromContent($content, 'landing', [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/features', 'label' => 'Features'],
            ['href' => MarketingFeatureDefinition::path($slug), 'label' => (string) ($content['nav_label'] ?? $slug)],
        ]);

        return $this->mergeLongFormSections($page, $content);
    }

    private function verticalPage(string $slug): ?array
    {
        $content = $this->verticals->forSlug($slug);

        if ($content === null) {
            return null;
        }

        return $this->landingPageFromContent($content, 'landing', [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/industries', 'label' => 'Industries'],
            ['href' => VerticalLandingDefinition::path($slug), 'label' => (string) ($content['nav_label'] ?? $slug)],
        ]);
    }

    private function comparePage(string $comparison): ?array
    {
        $slug = CompareLandingDefinition::slugFromComparison($comparison);

        if ($slug === null) {
            return null;
        }

        $content = $this->comparisons->forSlug($slug);

        if ($content === null) {
            return null;
        }

        $page = $this->landingPageFromContent($content, 'landing', [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/compare', 'label' => 'Compare'],
            ['href' => CompareLandingDefinition::path($slug), 'label' => (string) ($content['nav_label'] ?? $slug)],
        ]);

        $page = $this->mergeLongFormSections($page, $content);

        $extraSections = [];

        foreach (['us' => 'Helpefi pros', 'them' => ((string) ($content['competitor_name'] ?? 'Competitor')).' pros'] as $key => $fallbackTitle) {
            foreach ($content['pros'][$key] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $title = (string) ($item['title'] ?? '');
                $body = (string) ($item['body'] ?? '');

                if ($title !== '' || $body !== '') {
                    $extraSections[] = [
                        'title' => $fallbackTitle.': '.$title,
                        'body' => $body,
                    ];
                }
            }
        }

        foreach (['us' => 'Helpefi cons', 'them' => ((string) ($content['competitor_name'] ?? 'Competitor')).' cons'] as $key => $fallbackTitle) {
            foreach ($content['cons'][$key] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $title = (string) ($item['title'] ?? '');
                $body = (string) ($item['body'] ?? '');

                if ($title !== '' || $body !== '') {
                    $extraSections[] = [
                        'title' => $fallbackTitle.': '.$title,
                        'body' => $body,
                    ];
                }
            }
        }

        foreach ($content['who_them']['paragraphs'] ?? [] as $paragraph) {
            $body = trim((string) $paragraph);
            if ($body !== '') {
                $extraSections[] = [
                    'title' => (string) ($content['who_them']['title'] ?? 'Who should use the competitor'),
                    'body' => $body,
                ];
            }
        }

        foreach ($content['who_us']['paragraphs'] ?? [] as $paragraph) {
            $body = trim((string) $paragraph);
            if ($body !== '') {
                $extraSections[] = [
                    'title' => (string) ($content['who_us']['title'] ?? 'Who should use Helpefi'),
                    'body' => $body,
                ];
            }
        }

        $alternativesIntro = trim((string) ($content['alternatives']['intro'] ?? ''));
        if ($alternativesIntro !== '') {
            $extraSections[] = [
                'title' => (string) ($content['alternatives']['title'] ?? 'Related alternatives'),
                'body' => $alternativesIntro,
            ];
        }

        foreach ($content['alternatives']['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? '');
            $blurb = (string) ($item['blurb'] ?? '');
            $href = (string) ($item['href'] ?? '');

            if ($name === '' && $blurb === '') {
                continue;
            }

            $extraSections[] = [
                'title' => 'Alternative: '.$name,
                'body' => trim($blurb.($href !== '' ? ' '.$href : '')),
            ];
        }

        foreach ($content['migration']['steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $title = (string) ($step['title'] ?? '');
            $body = (string) ($step['body'] ?? '');

            if ($title !== '' || $body !== '') {
                $extraSections[] = ['title' => 'Migration: '.$title, 'body' => $body];
            }
        }

        foreach ($content['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $feature = trim((string) ($row['feature'] ?? ''));
            if ($feature === '') {
                continue;
            }

            $us = $row['us'] ?? '';
            $them = $row['them'] ?? '';
            $usText = is_bool($us) ? ($us ? 'Yes' : 'No') : (string) $us;
            $themText = is_bool($them) ? ($them ? 'Yes' : 'No') : (string) $them;
            $competitor = (string) ($content['competitor_name'] ?? 'Competitor');

            $extraSections[] = [
                'title' => 'Comparison: '.$feature,
                'body' => "Helpefi: {$usText}. {$competitor}: {$themText}.",
            ];
        }

        if ($extraSections !== []) {
            $page['sections'] = array_merge($page['sections'], $extraSections);
        }

        return $page;
    }

    private function integrationPage(string $slug): ?array
    {
        $content = $this->integrations->forSlug($slug);

        if ($content === null) {
            return null;
        }

        return $this->landingPageFromContent($content, 'landing', [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/integrations', 'label' => 'Integrations'],
            ['href' => IntegrationLandingDefinition::path($slug), 'label' => (string) ($content['nav_label'] ?? $slug)],
        ]);
    }

    private function migratePage(string $slug): ?array
    {
        $content = $this->migrations->forSlug($slug);

        if ($content === null) {
            return null;
        }

        $page = $this->landingPageFromContent($content, 'landing', [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/migrate', 'label' => 'Migrate'],
            ['href' => MigrateLandingDefinition::path($slug), 'label' => (string) ($content['nav_label'] ?? $slug)],
        ]);

        $steps = [];
        foreach ($content['steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $title = (string) ($step['title'] ?? '');
            $body = (string) ($step['body'] ?? '');

            if ($title !== '' || $body !== '') {
                $steps[] = ['title' => $title, 'body' => $body];
            }
        }

        if ($steps !== []) {
            $page['sections'] = array_merge($page['sections'], $steps);
        }

        return $page;
    }

    private function hubPage(array $hub, string $type, array $links, array $breadcrumbs): array
    {
        return [
            'type' => 'hub',
            'badge' => (string) ($hub['badge'] ?? ''),
            'h1' => (string) ($hub['hero_title'] ?? ''),
            'h1Highlight' => (string) ($hub['hero_highlight'] ?? ''),
            'subtitle' => (string) ($hub['hero_subtitle'] ?? ''),
            'links' => $links,
            'breadcrumbs' => $breadcrumbs,
            'ctaTitle' => (string) ($hub['cta_title'] ?? ''),
            'ctaBody' => (string) ($hub['cta_body'] ?? ''),
        ];
    }

    private function featuresHubPage(): array
    {
        $hub = app(MarketingStaticContentService::class)->featuresHub();
        $links = collect($this->features->navigation())
            ->map(fn (array $item) => [
                'href' => $item['path'],
                'label' => $item['nav_label'],
                'description' => $item['hero_subtitle'] ?? '',
            ])
            ->all();

        return $this->hubPage($hub, 'hub', $links, [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/features', 'label' => 'Features'],
        ]);
    }

    private function compareHubPage(): array
    {
        $hub = $this->comparisons->hub();
        $links = collect($this->comparisons->navigation())
            ->map(fn (array $item) => [
                'href' => $item['path'],
                'label' => $item['footer_label'] ?? $item['nav_label'],
                'description' => '',
            ])
            ->all();

        return $this->hubPage($hub, 'hub', $links, [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/compare', 'label' => 'Compare'],
        ]);
    }

    private function migrateHubPage(): array
    {
        $hub = $this->migrations->hub();
        $links = collect($this->migrations->navigation())
            ->map(fn (array $item) => [
                'href' => $item['path'],
                'label' => $item['source_name'] ?? $item['nav_label'],
                'description' => '',
            ])
            ->all();

        return $this->hubPage($hub, 'hub', $links, [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/migrate', 'label' => 'Migrate'],
        ]);
    }

    private function integrationsHubPage(): array
    {
        $static = $this->staticContent->forSlug('integrations');
        $hub = $this->integrations->hub();
        $links = collect($this->integrations->navigation())
            ->map(fn (array $item) => [
                'href' => $item['path'],
                'label' => $item['nav_label'],
                'description' => $item['hero_subtitle'] ?? '',
            ])
            ->all();

        $page = $this->hubPage(
            $static !== null ? array_merge($hub, [
                'hero_title' => $static['hero_title'] ?? $hub['hero_title'] ?? '',
                'hero_subtitle' => $static['hero_subtitle'] ?? $hub['hero_subtitle'] ?? '',
            ]) : $hub,
            'hub',
            $links,
            [
                ['href' => '/', 'label' => 'Home'],
                ['href' => '/integrations', 'label' => 'Integrations'],
            ],
        );

        $page['sections'] = [];

        if (is_array($static)) {
            foreach ($static['sections'] ?? [] as $section) {
                if (! is_array($section)) {
                    continue;
                }

                $page['sections'][] = [
                    'title' => (string) ($section['title'] ?? ''),
                    'body' => (string) ($section['body'] ?? ''),
                ];
            }
        }

        return $page;
    }

    private function industriesHubPage(): ?array
    {
        $static = $this->staticContent->forSlug('industries');

        if ($static === null) {
            return null;
        }

        $links = collect($this->verticals->navigation())
            ->map(fn (array $item) => [
                'href' => $item['path'],
                'label' => $item['nav_label'],
                'description' => $item['hero_subtitle'] ?? '',
            ])
            ->all();

        $page = [
            'type' => 'hub',
            'h1' => (string) ($static['hero_title'] ?? ''),
            'subtitle' => (string) ($static['hero_subtitle'] ?? ''),
            'links' => $links,
            'breadcrumbs' => [
                ['href' => '/', 'label' => 'Home'],
                ['href' => '/industries', 'label' => 'Industries'],
            ],
            'sections' => [],
        ];

        foreach ($static['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            $page['sections'][] = [
                'title' => (string) ($section['title'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
            ];
        }

        return $page;
    }

    private function blogIndexPage(): array
    {
        $posts = MarketingBlogDefinition::forIndex();
        $links = collect($posts)
            ->map(fn (array $post) => [
                'href' => $post['path'],
                'label' => $post['title'],
                'description' => $post['excerpt'] ?? '',
            ])
            ->all();

        return [
            'type' => 'hub',
            'h1' => 'Helpefi Blog',
            'subtitle' => 'Guides on AI helpdesk software, ticket management, ITSM, and customer support best practices.',
            'links' => $links,
            'breadcrumbs' => [
                ['href' => '/', 'label' => 'Home'],
                ['href' => '/blog', 'label' => 'Blog'],
            ],
        ];
    }

    private function blogPostPage(string $slug): ?array
    {
        $post = MarketingBlogDefinition::find($slug);

        if ($post === null) {
            return null;
        }

        $bodyText = $this->plainText((string) ($post['body'] ?? ''));
        $excerpt = (string) ($post['excerpt'] ?? '');

        if ($bodyText === '' && $excerpt !== '') {
            $bodyText = $excerpt;
        }

        return [
            'type' => 'article',
            'h1' => (string) ($post['title'] ?? ''),
            'subtitle' => $excerpt,
            'body' => $bodyText,
            'breadcrumbs' => [
                ['href' => '/', 'label' => 'Home'],
                ['href' => '/blog', 'label' => 'Blog'],
                ['href' => $post['path'], 'label' => (string) ($post['title'] ?? '')],
            ],
        ];
    }

    private function staticPage(string $slug): ?array
    {
        $content = $this->staticContent->forSlug($slug, config('marketing_seo.organization.contact_email'));

        if ($content === null) {
            return null;
        }

        $path = MarketingStaticPageDefinition::path($slug);
        $sections = [];

        foreach ($content['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            $sections[] = [
                'title' => (string) ($section['title'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
            ];
        }

        $page = [
            'type' => 'static',
            'h1' => (string) ($content['hero_title'] ?? ''),
            'subtitle' => (string) ($content['hero_subtitle'] ?? ''),
            'sections' => $sections,
            'ctaTitle' => (string) ($content['cta_title'] ?? ''),
            'ctaBody' => (string) ($content['cta_body'] ?? ''),
            'breadcrumbs' => [
                ['href' => '/', 'label' => 'Home'],
                ['href' => $path, 'label' => (string) ($content['nav_label'] ?? $slug)],
            ],
        ];

        $faqs = [];
        foreach ($content['faq'] ?? [] as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $q = (string) ($faq['q'] ?? '');
            $a = (string) ($faq['a'] ?? '');
            if ($q !== '' && $a !== '') {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
        }

        if ($faqs !== []) {
            $page['faqs'] = $faqs;
        }

        return $this->mergeLongFormSections($page, $content);
    }

    private function mergeLongFormSections(array $page, array $content): array
    {
        $extraSections = [];

        $intro = trim((string) ($content['intro'] ?? ''));
        if ($intro !== '') {
            $extraSections[] = [
                'title' => 'Overview',
                'body' => $intro,
            ];
        }

        foreach ($content['deep_dives'] ?? [] as $dive) {
            if (! is_array($dive)) {
                continue;
            }

            $title = (string) ($dive['title'] ?? '');
            $body = (string) ($dive['body'] ?? '');

            if ($title !== '' || $body !== '') {
                $extraSections[] = ['title' => $title, 'body' => $body];
            }
        }

        foreach ($content['use_cases']['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = (string) ($item['title'] ?? '');
            $body = (string) ($item['body'] ?? '');

            if ($title !== '' || $body !== '') {
                $extraSections[] = [
                    'title' => 'Use case: '.$title,
                    'body' => $body,
                ];
            }
        }

        $conclusion = trim((string) ($content['conclusion']['body'] ?? ''));
        if ($conclusion !== '') {
            $extraSections[] = [
                'title' => (string) ($content['conclusion']['title'] ?? 'Conclusion'),
                'body' => $conclusion,
            ];
        }

        if ($extraSections !== []) {
            $page['sections'] = array_merge($page['sections'] ?? [], $extraSections);
        }

        return $page;
    }

    private function navigation(): array
    {
        return [
            'features' => $this->features->navigation(),
            'verticals' => $this->verticals->navigation(),
            'comparisons' => $this->comparisons->navigation(),
            'integrations' => $this->integrations->navigation(),
            'migrations' => $this->migrations->navigation(),
            'hubs' => [
                ['href' => '/features', 'label' => 'Features'],
                ['href' => '/integrations', 'label' => 'Integrations'],
                ['href' => '/industries', 'label' => 'Industries'],
                ['href' => '/compare', 'label' => 'Compare'],
                ['href' => '/migrate', 'label' => 'Migrate'],
                ['href' => '/blog', 'label' => 'Blog'],
                ['href' => '/pricing', 'label' => 'Pricing'],
            ],
        ];
    }

    private function plainText(string $markdown): string
    {
        $text = strip_tags($markdown);
        $text = preg_replace('/```[\s\S]*?```/', ' ', $text) ?? $text;
        $text = preg_replace('/`[^`]+`/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^#+\s+/m', '', $text) ?? $text;
        $text = preg_replace('/[*_~>#-]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
