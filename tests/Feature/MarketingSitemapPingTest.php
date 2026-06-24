<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MarketingSitemapPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_sitemap_dry_run_prints_search_engine_urls(): void
    {
        config(['marketing_seo.site_url' => 'https://helpefi.com']);

        $exitCode = Artisan::call('marketing:ping-sitemap', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[dry-run] Google:', $output);
        $this->assertStringContainsString('[dry-run] Bing:', $output);
        $this->assertStringContainsString('helpefi.com', $output);
    }
}
