<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Support\VerticalLandingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerticalLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_vertical_landing_pages_render(): void
    {
        foreach (VerticalLandingDefinition::slugs() as $slug) {
            $this->get('http://'.config('tenancy.central_app_domain').VerticalLandingDefinition::path($slug))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/VerticalLanding')
                    ->where('vertical', $slug)
                );
        }
    }

    public function test_unknown_vertical_returns_not_found(): void
    {
        $this->get('http://'.config('tenancy.central_app_domain').'/for/not-a-real-vertical')
            ->assertNotFound();
    }

    public function test_sitemap_includes_vertical_pages(): void
    {
        config(['marketing_seo.site_url' => 'http://'.config('tenancy.central_app_domain')]);

        $response = $this->get('http://'.config('tenancy.central_app_domain').'/sitemap.xml');

        $response->assertOk();

        foreach (VerticalLandingDefinition::slugs() as $slug) {
            $response->assertSee(config('marketing_seo.site_url').VerticalLandingDefinition::path($slug), false);
        }
    }

    public function test_vertical_page_has_seo_meta_tags(): void
    {
        config(['marketing_seo.site_url' => 'http://'.config('tenancy.central_app_domain')]);

        $url = 'http://'.config('tenancy.central_app_domain').'/for/ecommerce';

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee($url, false);
        $response->assertSee('application/ld+json', false);
    }
}
