<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Services\CentralSeoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ImageSitemapController extends Controller
{
    public function __invoke(CentralSeoService $seo): Response
    {
        $version = (int) Cache::get('central:sitemap:version', 1);
        $cacheKey = "central:sitemap:image:urlset:v{$version}";

        $xml = Cache::remember($cacheKey, now()->addHours(6), function () use ($seo): string {
            $entries = $seo->imageSitemapEntries();

            $urls = collect($entries)->map(function (array $entry): string {
                $loc = htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
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

