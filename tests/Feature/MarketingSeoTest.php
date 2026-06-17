<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Domains\Tenancy\Support\MarketingFeatureDefinition;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
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
                );
        }
    }

    public function test_static_marketing_pages_render(): void
    {
        config(['marketing_seo.organization.contact_email' => 'hello@helpefi.com']);

        $this->get($this->centralUrl('/contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Central/Contact'));

        foreach (MarketingStaticPageDefinition::slugs() as $slug) {
            if ($slug === 'contact') {
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

    public function test_sitemap_includes_marketing_pages(): void
    {
        $response = $this->get($this->centralUrl('/sitemap.xml'));

        $response->assertOk();

        foreach (MarketingStaticPageDefinition::slugs() as $slug) {
            $response->assertSee(MarketingStaticPageDefinition::path($slug), false);
        }

        foreach (MarketingFeatureDefinition::slugs() as $slug) {
            $response->assertSee(MarketingFeatureDefinition::path($slug), false);
        }

        $response->assertSee('/blog', false);

        foreach (MarketingBlogDefinition::slugs() as $slug) {
            $response->assertSee(MarketingBlogDefinition::path($slug), false);
        }
    }

    public function test_robots_txt_blocks_internal_paths(): void
    {
        $response = $this->get($this->centralUrl('/robots.txt'));

        $response->assertOk();
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Sitemap:', false);
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
        $response->assertSee('AI Helpdesk Software for Modern Teams', false);
    }

    public function test_feature_page_has_canonical_and_json_ld(): void
    {
        config(['marketing_seo.site_url' => 'https://helpefi.com']);

        $response = $this->get($this->centralUrl('/features/ai'));

        $response->assertOk();
        $response->assertSee('https://helpefi.com/features/ai', false);
        $response->assertSee('application/ld+json', false);
    }

    public function test_login_is_noindex(): void
    {
        $response = $this->get($this->centralUrl('/login'));

        $response->assertOk();
        $response->assertSee('noindex, follow', false);
    }
}
