<?php

namespace Tests\Feature;

use App\Domains\Platform\Jobs\RunMarketingSeoAuditJob;
use App\Domains\Platform\Services\MarketingSeoAuditService;
use App\Domains\Tenancy\Support\MarketingStaticPageDefinition;
use Database\Seeders\MarketingBlogPostSeeder;
use Database\Seeders\PlatformPermissionSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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

    private function sampleReport(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'site_url' => 'https://helpefi.com',
            'pages_scanned' => 12,
            'summary' => [
                'health_score' => 88,
                'total_issues' => 2,
                'missing_h1' => 0,
                'duplicate_h1' => 0,
                'missing_alt' => 0,
                'missing_title' => 0,
                'duplicate_title' => 0,
                'missing_description' => 0,
                'broken_links' => 0,
                'redirect_chains' => 0,
                'canonical_errors' => 0,
                'large_images' => 0,
                'missing_schema' => 0,
                'description_too_long' => 0,
                'description_too_short' => 0,
                'title_too_short' => 0,
                'low_word_count' => 0,
                'no_outgoing_links' => 0,
                'orphan_page' => 0,
            ],
            'issues' => [],
            'pages' => [],
        ];
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
        Cache::forget(MarketingSeoAuditService::CACHE_KEY);

        $this->adminLogin();

        $startedAt = microtime(true);

        $this->get($this->centralUrl('/admin/dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Dashboard')
                ->missing('dashboard.marketing_seo_audit')
            );

        $this->assertLessThan(5, microtime(true) - $startedAt);
        $this->assertNull(Cache::get(MarketingSeoAuditService::CACHE_KEY));
    }

    public function test_admin_seo_audit_page_does_not_run_full_audit_on_cache_miss(): void
    {
        Cache::forget(MarketingSeoAuditService::CACHE_KEY);
        Cache::forget(MarketingSeoAuditService::CACHE_RUNNING_KEY);

        $this->adminLogin();

        $startedAt = microtime(true);

        $this->get($this->centralUrl('/admin/seo-audit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Seo/Audit')
                ->where('auditStatus', 'pending')
                ->where('report', null)
            );

        $this->assertLessThan(2, microtime(true) - $startedAt);
        $this->assertNull(Cache::get(MarketingSeoAuditService::CACHE_KEY));
    }

    public function test_admin_can_view_cached_seo_audit_report(): void
    {
        Cache::put(MarketingSeoAuditService::CACHE_KEY, $this->sampleReport(), now()->addHour());

        $this->adminLogin();

        $this->get($this->centralUrl('/admin/seo-audit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Central/Admin/Seo/Audit')
                ->where('auditStatus', 'ready')
                ->has('report.summary')
                ->has('report.pages')
            );
    }

    public function test_admin_can_queue_seo_audit_refresh(): void
    {
        Queue::fake();

        $this->adminLogin();

        $this->post($this->centralUrl('/admin/seo-audit'))
            ->assertRedirect($this->centralUrl('/admin/seo-audit'));

        Queue::assertPushed(RunMarketingSeoAuditJob::class);
        $this->assertTrue(Cache::get(MarketingSeoAuditService::CACHE_RUNNING_KEY));
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
