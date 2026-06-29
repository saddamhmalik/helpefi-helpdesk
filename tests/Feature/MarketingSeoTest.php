<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Support\CompareLandingDefinition;
use App\Domains\Tenancy\Support\IntegrationLandingDefinition;
use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use App\Domains\Tenancy\Support\MigrateLandingDefinition;
use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use App\Domains\Platform\Models\MarketingPageContent;
use Database\Seeders\MarketingBlogPostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketingBlogPostSeeder::class);
    }

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    public function test_feature_landing_pages_render(): void
    {
        foreach (MarketingFeatureDefinition::slugs() as $slug) {
            $this->get($this->centralUrl(MarketingFeatureDefinition::path($slug)))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/FeatureLanding')
                    ->where('feature', $slug)
                    ->has('content.features')
                    ->has('content.faq')
                );
        }
    }

    public function test_integration_landing_pages_render(): void
    {
        foreach (IntegrationLandingDefinition::slugs() as $slug) {
            $this->get($this->centralUrl(IntegrationLandingDefinition::path($slug)))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/IntegrationLanding')
                    ->where('integration', $slug)
                    ->has('content.features')
                    ->has('content.faq')
                );
        }
    }

    public function test_compare_landing_pages_render(): void
    {
        foreach (CompareLandingDefinition::slugs() as $slug) {
            $this->get($this->centralUrl(CompareLandingDefinition::path($slug)))
                ->assertOk();
        }
    }

    public function test_legacy_feature_urls_redirect(): void
    {
        $this->get($this->centralUrl('/features/ai'))
            ->assertRedirect($this->centralUrl('/ai-agent'));

        $this->get($this->centralUrl('/vs/zendesk'))
            ->assertRedirect($this->centralUrl('/compare/zendesk-vs-helpefi'));
    }

    public function test_integrations_index_page_renders(): void
    {
        $this->get($this->centralUrl('/integrations'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/IntegrationsIndex')
                ->has('integrationPages', count(IntegrationLandingDefinition::slugs()))
            );
    }

    public function test_static_marketing_pages_render(): void
    {
        config(['marketing_seo.organization.contact_email' => 'hello@helpefi.com']);

        $this->get($this->centralUrl('/contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Contact'));

        foreach (MarketingStaticPageDefinition::slugs() as $slug) {
            if (in_array($slug, ['contact', 'integrations'], true)) {
                continue;
            }

            $this->get($this->centralUrl(MarketingStaticPageDefinition::path($slug)))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/MarketingStaticPage')
                    ->where('page', $slug)
                );
        }
    }

    public function test_blog_pages_render(): void
    {
        $this->get($this->centralUrl('/blog'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Blog/Index'));

        foreach (MarketingBlogDefinition::slugs() as $slug) {
            $this->get($this->centralUrl(MarketingBlogDefinition::path($slug)))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/Blog/Show')
                    ->where('post.slug', $slug)
                );
        }
    }

    public function test_features_index_page_renders(): void
    {
        $this->get($this->centralUrl('/features'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/FeaturesIndex'));
    }

    public function test_sitemap_includes_marketing_pages(): void
    {
        $response = $this->get($this->centralUrl('/sitemap.xml'));

        $response->assertOk();

        foreach (MarketingStaticPageDefinition::slugs() as $slug) {
            $response->assertSee(MarketingStaticPageDefinition::path($slug), false);
        }

        $response->assertSee('/features', false);

        foreach (MarketingFeatureDefinition::slugs() as $slug) {
            $response->assertSee(MarketingFeatureDefinition::path($slug), false);
        }

        foreach (IntegrationLandingDefinition::slugs() as $slug) {
            $response->assertSee(IntegrationLandingDefinition::path($slug), false);
        }

        foreach (CompareLandingDefinition::slugs() as $slug) {
            $response->assertSee(CompareLandingDefinition::path($slug), false);
        }

        $response->assertSee('/blog', false);

        foreach (MarketingBlogDefinition::slugs() as $slug) {
            $response->assertSee(MarketingBlogDefinition::path($slug), false);
        }

        foreach (MigrateLandingDefinition::slugs() as $slug) {
            $response->assertSee(MigrateLandingDefinition::path($slug), false);
        }
    }

    public function test_robots_txt_blocks_internal_paths(): void
    {
        $response = $this->get($this->centralUrl('/robots.txt'));

        $response->assertOk();
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /register', false);
        $response->assertSee('Disallow: /dashboard', false);
        $response->assertSee('Disallow: /api', false);
        $response->assertSee('Disallow: /storage', false);
        $response->assertSee('Sitemap:', false);
    }

    public function test_robots_txt_disallows_all_on_staging(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $response = $this->get($this->centralUrl('/robots.txt'));

        $response->assertOk();
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Disallow: /', false);

        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_home_has_server_rendered_seo_meta(): void
    {
        config(['marketing_seo.site_url' => 'https://helpefi.com']);

        $response = $this->get($this->centralUrl('/'));

        $response->assertOk();
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('https://helpefi.com/', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('AI Helpdesk Software — Tickets, Chat, KB', false);
    }

    public function test_feature_page_has_canonical_and_json_ld(): void
    {
        config(['marketing_seo.site_url' => 'https://helpefi.com']);

        $response = $this->get($this->centralUrl('/ai-agent'));

        $response->assertOk();
        $response->assertSee('https://helpefi.com/ai-agent', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('<h1', false);
        $response->assertSee('AI agent that assists', false);
        $response->assertSee('href="/features"', false);
    }

    public function test_marketing_first_paint_includes_footer_links(): void
    {
        $response = $this->get($this->centralUrl('/shared-inbox'));

        $response->assertOk();
        $response->assertSee('id="marketing-first-paint"', false);
        $response->assertSee('href="/pricing"', false);
        $response->assertSee('href="/blog"', false);
    }

    public function test_login_is_noindex(): void
    {
        $response = $this->get($this->centralUrl('/login'));

        $response->assertOk();
        $response->assertSee('noindex, follow', false);
    }

    public function test_compare_hub_page_renders(): void
    {
        $this->get($this->centralUrl('/compare'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/CompareIndex')
                ->has('comparePages', count(CompareLandingDefinition::slugs()))
            );
    }

    public function test_migrate_hub_page_renders(): void
    {
        $this->get($this->centralUrl('/migrate'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/MigrateIndex')
                ->has('migratePages', count(MigrateLandingDefinition::slugs()))
            );
    }

    public function test_sitemap_includes_compare_and_migrate_hubs(): void
    {
        $response = $this->get($this->centralUrl('/sitemap.xml'));

        $response->assertOk();
        $response->assertSee('/compare', false);
        $response->assertSee('/migrate', false);
    }

    public function test_db_published_vertical_is_indexable_and_listed(): void
    {
        MarketingPageContent::query()->create([
            'page_type' => 'vertical',
            'slug' => 'retail',
            'content' => [
                'nav_label' => 'Retail',
                'badge' => 'Helpdesk for retail',
                'hero_title' => 'Helpdesk for retail brands',
                'hero_highlight' => 'Omnichannel support',
                'hero_subtitle' => 'Route store, ecommerce, and warehouse support in one queue.',
                'pains' => [
                    ['title' => 'Channel sprawl', 'body' => 'Email, chat, and social requests lack shared context.'],
                ],
                'features' => [
                    ['title' => 'Order-aware tickets', 'body' => 'Surface order and shipment data beside every ticket.'],
                ],
                'faq' => [
                    ['q' => 'Can we support multiple store brands?', 'a' => 'Yes — use multi-brand portals and routing rules.'],
                ],
                'cta_title' => 'Support every store channel',
                'cta_body' => 'Start a free trial and connect your retail inbox.',
            ],
            'status' => MarketingPageContent::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get($this->centralUrl('/helpdesk-for-retail'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/VerticalLanding')
                ->where('vertical', 'retail')
            );

        $this->get($this->centralUrl('/sitemap.xml'))
            ->assertOk()
            ->assertSee('/helpdesk-for-retail', false);

        $this->get($this->centralUrl('/industries'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/MarketingStaticPage')
                ->where('page', 'industries')
                ->has('verticalPages', count(VerticalLandingDefinition::slugs()) + 1)
            );
    }
}
