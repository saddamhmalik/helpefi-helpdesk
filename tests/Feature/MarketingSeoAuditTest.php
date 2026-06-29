<?php

namespace Tests\Feature;

use App\Domains\Platform\Services\MarketingSeoAuditService;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use Database\Seeders\MarketingBlogPostSeeder;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketingSeoAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MarketingBlogPostSeeder::class,
            PlatformPermissionSeeder::class,
            PlatformUserSeeder::class,
        ]);
    }

    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_app_domain').$path;
    }

    private function adminLogin(): void
    {
        $this->post($this->centralUrl('/admin/login'), [
            'email' => PlatformUserSeeder::DEFAULT_EMAIL,
            'password' => PlatformUserSeeder::DEFAULT_PASSWORD,
        ]);
    }

    public function test_seo_audit_scans_marketing_pages(): void
    {
        config(['marketing_seo.site_url' => $this->centralUrl()]);

        $report = app(MarketingSeoAuditService::class)->run(fresh: true);

        $this->assertGreaterThan(10, $report['pages_scanned']);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('health_score', $report['summary']);
        $this->assertArrayHasKey('issues', $report);
        $this->assertArrayHasKey('pages', $report);

        $home = collect($report['pages'])->firstWhere('path', '/');
        $this->assertNotNull($home);
        $this->assertSame(200, $home['status']);
        $this->assertNotSame('', $home['title']);
        $this->assertTrue($home['schema']);
    }

    public function test_dashboard_does_not_run_full_seo_audit_on_cache_miss(): void
    {
        Cache::forget('marketing:seo:audit:v1');

        $this->adminLogin();

        $startedAt = microtime(true);

        $this->get($this->centralUrl('/admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Dashboard')
                ->missing('dashboard.marketing_seo_audit')
            );

        $this->assertLessThan(5, microtime(true) - $startedAt);
        $this->assertNull(Cache::get('marketing:seo:audit:v1'));
    }

    public function test_admin_can_view_seo_audit_report(): void
    {
        config(['marketing_seo.site_url' => $this->centralUrl()]);

        $this->adminLogin();

        $this->get($this->centralUrl('/admin/seo-audit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Seo/Audit')
                ->has('report.summary')
                ->has('report.pages')
            );
    }

    public function test_admin_can_refresh_seo_audit(): void
    {
        config(['marketing_seo.site_url' => $this->centralUrl()]);

        $this->adminLogin();

        $this->post($this->centralUrl('/admin/seo-audit'))
            ->assertRedirect($this->centralUrl('/admin/seo-audit'));
    }

    public function test_sitemap_pages_have_titles_and_descriptions(): void
    {
        config(['marketing_seo.site_url' => $this->centralUrl()]);

        $report = app(MarketingSeoAuditService::class)->run(fresh: true);

        foreach (MarketingStaticPageDefinition::slugs() as $slug) {
            if ($slug === 'contact') {
                continue;
            }

            $path = MarketingStaticPageDefinition::path($slug);
            $page = collect($report['pages'])->firstWhere('path', $path);

            $this->assertNotNull($page, "Missing audit page for {$path}");
            $this->assertNotSame('', $page['title'], "Missing title for {$path}");
            $this->assertNotSame('', $page['description'], "Missing description for {$path}");
        }
    }
}
