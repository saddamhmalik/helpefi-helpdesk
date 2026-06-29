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
            'swapHero' => $request->routeIs('central.home'),
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

        return [
            'type' => 'home',
            'h1' => $home['hero_title_line1'] ?? '',
            'h1Highlight' => $home['hero_title_line2'] ?? '',
            'subtitle' => $home['hero_subtitle'] ?? '',
            'ctaPrimary' => $home['hero_cta_long'] ?? '',
            'ctaSecondary' => $home['hero_try_ai'] ?? '#ai',
            'ctaSecondaryHref' => '#ai',
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
        ];
    }

    private function featurePage(string $slug): ?array
    {
        $content = $this->features->forSlug($slug);

        if ($content === null) {
            return null;
        }

        return $this->landingPageFromContent($content, 'landing', [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/features', 'label' => 'Features'],
            ['href' => MarketingFeatureDefinition::path($slug), 'label' => (string) ($content['nav_label'] ?? $slug)],
        ]);
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

        return $this->landingPageFromContent($content, 'landing', [
            ['href' => '/', 'label' => 'Home'],
            ['href' => '/compare', 'label' => 'Compare'],
            ['href' => CompareLandingDefinition::path($slug), 'label' => (string) ($content['nav_label'] ?? $slug)],
        ]);
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

        return [
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
