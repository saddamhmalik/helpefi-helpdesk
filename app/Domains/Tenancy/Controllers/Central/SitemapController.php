<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\CentralSeoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(CentralSeoService $seo, ?int $chunk = null): Response
    {
        $version = (int) Cache::get('central:sitemap:version', 1);
        $entries = $seo->sitemapEntries();
        $chunkSize = 50000;

        if (count($entries) > $chunkSize && $chunk === null) {
            $xml = Cache::remember("central:sitemap:index:v{$version}", now()->addHours(6), function () use ($seo, $entries, $chunkSize): string {
                $chunks = (int) ceil(count($entries) / $chunkSize);
                $sitemaps = collect(range(1, max(1, $chunks)))->map(function (int $number) use ($seo): string {
                    $loc = htmlspecialchars($seo->siteUrl()."/sitemap-{$number}.xml", ENT_XML1 | ENT_QUOTES, 'UTF-8');

                    return <<<XML
  <sitemap>
    <loc>{$loc}</loc>
  </sitemap>
XML;
                })->implode("\n");

                return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$sitemaps}
</sitemapindex>
XML;
            });

            return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }

        $page = max(1, (int) ($chunk ?? 1));
        $slice = array_slice($entries, ($page - 1) * $chunkSize, $chunkSize);

        $cacheKey = $chunk === null
            ? "central:sitemap:urlset:v{$version}"
            : "central:sitemap:urlset:v{$version}:{$page}";

        $xml = Cache::remember($cacheKey, now()->addHours(6), function () use ($seo, $slice): string {
            $urls = collect($slice)->map(function (array $entry): string {
            $loc = htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $changefreq = htmlspecialchars($entry['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $priority = htmlspecialchars($entry['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lastmod = htmlspecialchars($entry['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $images = collect($entry['images'] ?? [])->map(function (array $image): string {
                $imgLoc = htmlspecialchars((string) ($image['loc'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

                if ($imgLoc === '') {
                    return '';
                }

                return <<<XML
    <image:image>
      <image:loc>{$imgLoc}</image:loc>
    </image:image>
XML;
            })->filter()->implode("\n");

            return <<<XML
  <url>
    <loc>{$loc}</loc>
    <lastmod>{$lastmod}</lastmod>
    <changefreq>{$changefreq}</changefreq>
    <priority>{$priority}</priority>
{$images}
  </url>
XML;
            })->implode("\n");

            return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
{$urls}
</urlset>
XML;
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
