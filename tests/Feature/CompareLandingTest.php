<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Support\CompareLandingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_compare_landing_pages_render(): void
    {
        $competitorComparisonSlugs = ['zendesk', 'freshdesk', 'freshservice', 'zoho-desk', 'intercom', 'front', 'help-scout'];

        foreach ($competitorComparisonSlugs as $slug) {
            $this->get('http://'.config('tenancy.central_app_domain').CompareLandingDefinition::path($slug))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/CompetitorComparison')
                    ->where('competitorSlug', $slug)
                    ->has('matrix.rows')
                );
        }

        foreach (array_diff(CompareLandingDefinition::slugs(), $competitorComparisonSlugs) as $slug) {
            $this->get('http://'.config('tenancy.central_app_domain').CompareLandingDefinition::path($slug))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/CompareLanding')
                    ->where('competitor', $slug)
                    ->has('content.reasons', 3)
                    ->has('content.rows')
                    ->has('content.faq')
                );
        }
    }

    public function test_home_page_includes_compare_navigation(): void
    {
        $this->get('http://'.config('tenancy.central_app_domain').'/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Home')
                ->has('comparePages', count(CompareLandingDefinition::slugs()))
                ->where('comparePages.0.footer_label', 'vs Zendesk')
            );
    }

    public function test_unknown_compare_page_returns_not_found(): void
    {
        $this->get('http://'.config('tenancy.central_app_domain').'/compare/not-a-real-competitor-vs-helpefi')
            ->assertNotFound();
    }

    public function test_sitemap_includes_compare_pages(): void
    {
        config(['marketing_seo.site_url' => 'http://'.config('tenancy.central_app_domain')]);

        $response = $this->get('http://'.config('tenancy.central_app_domain').'/sitemap.xml');

        $response->assertOk();

        foreach (CompareLandingDefinition::slugs() as $slug) {
            $response->assertSee(config('marketing_seo.site_url').CompareLandingDefinition::path($slug), false);
        }
    }

    public function test_compare_page_has_seo_meta_tags(): void
    {
        config(['marketing_seo.site_url' => 'http://'.config('tenancy.central_app_domain')]);

        $url = 'http://'.config('tenancy.central_app_domain').CompareLandingDefinition::path('freshdesk');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee($url, false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('Freshdesk alternative', false);
    }
}
