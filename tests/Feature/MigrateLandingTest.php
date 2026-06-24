<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Support\MigrateLandingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrateLandingTest extends TestCase
{
    use RefreshDatabase;

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    public function test_migrate_landing_pages_render(): void
    {
        foreach (MigrateLandingDefinition::slugs() as $slug) {
            $this->get($this->centralUrl(MigrateLandingDefinition::path($slug)))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Central/MigrateLanding')
                    ->where('source', $slug)
                );
        }
    }

    public function test_unknown_migration_source_returns_not_found(): void
    {
        $this->get($this->centralUrl('/migrate/from-not-a-real-tool'))
            ->assertNotFound();
    }

    public function test_sitemap_includes_migrate_pages(): void
    {
        config(['marketing_seo.site_url' => 'http://'.config('tenancy.central_app_domain')]);

        $response = $this->get($this->centralUrl('/sitemap.xml'));

        $response->assertOk();

        foreach (MigrateLandingDefinition::slugs() as $slug) {
            $response->assertSee(
                config('marketing_seo.site_url').MigrateLandingDefinition::path($slug),
                false
            );
        }
    }

    public function test_migrate_page_has_seo_meta_tags(): void
    {
        config(['marketing_seo.site_url' => 'http://'.config('tenancy.central_app_domain')]);

        $url = $this->centralUrl('/migrate/from-zendesk');

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee($url, false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('Migrate from Zendesk', false);
    }

    public function test_migrate_page_json_ld_replaces_brand_placeholder(): void
    {
        config(['marketing_seo.site_url' => 'http://'.config('tenancy.central_app_domain')]);

        $response = $this->get($this->centralUrl('/migrate/from-freshdesk'));

        $response->assertOk();
        $response->assertDontSee('{brand}', false);
        $response->assertSee('Can Helpefi replace Freshdesk and Freshservice?', false);
    }

    public function test_migrate_page_uses_page_specific_og_image_when_configured(): void
    {
        config([
            'marketing_seo.site_url' => 'https://helpefi.com',
            'marketing_seo.og_images.migrate_zendesk' => '/og/migrate-zendesk.png',
        ]);

        $response = $this->get($this->centralUrl('/migrate/from-zendesk'));

        $response->assertOk();
        $response->assertSee('https://helpefi.com/og/migrate-zendesk.png', false);
    }
}
