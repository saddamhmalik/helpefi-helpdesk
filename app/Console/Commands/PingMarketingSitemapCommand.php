<?php

namespace App\Console\Commands;

use App\Domains\Tenancy\Services\CentralSeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PingMarketingSitemapCommand extends Command
{
    protected $signature = 'marketing:ping-sitemap {--dry-run : Print ping URLs without requesting them}';

    protected $description = 'Notify Google and Bing that the marketing sitemap was updated';

    public function handle(CentralSeoService $seo): int
    {
        $sitemapUrl = urlencode($seo->siteUrl().'/sitemap.xml');
        $endpoints = [
            'Google' => 'https://www.google.com/ping?sitemap='.$sitemapUrl,
            'Bing' => 'https://www.bing.com/ping?sitemap='.$sitemapUrl,
        ];

        foreach ($endpoints as $engine => $url) {
            if ($this->option('dry-run')) {
                $this->line("[dry-run] {$engine}: {$url}");

                continue;
            }

            try {
                $response = Http::timeout(10)->get($url);
                $this->info("{$engine}: HTTP {$response->status()}");
            } catch (\Throwable $exception) {
                $this->warn("{$engine}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
