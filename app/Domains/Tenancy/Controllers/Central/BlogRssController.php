<?php

namespace App\Domains\Tenancy\Controllers\Central;

use App\Domains\Tenancy\Support\MarketingBlogDefinition;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class BlogRssController extends Controller
{
    public function __invoke(): Response
    {
        $configured = rtrim((string) config('app.url'), '/');
        $siteUrl = $configured !== '' ? $configured : 'http://'.rtrim((string) config('tenancy.central_app_domain'), '/');
        $title = (string) config('app.name', 'Helpefi').' Blog';
        $description = 'Latest blog posts and updates.';
        $lastBuildDate = date(DATE_RSS);

        $posts = MarketingBlogDefinition::all();
        $posts = array_slice($posts, 0, 50);

        $items = [];
        foreach ($posts as $post) {
            $link = $siteUrl.(string) ($post['path'] ?? '');
            $itemTitle = htmlspecialchars((string) ($post['title'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $pubDateRaw = (string) ($post['published_at'] ?? '');
            $pubDate = $pubDateRaw !== ''
                ? date(DATE_RSS, strtotime($pubDateRaw.' UTC'))
                : date(DATE_RSS);
            $itemDesc = htmlspecialchars((string) ($post['excerpt'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $categories = collect($post['categories'] ?? [])
                ->map(fn (mixed $c) => is_array($c) ? ($c['name'] ?? null) : null)
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->values()
                ->all();

            $itemCategoriesXml = '';
            foreach ($categories as $cat) {
                $itemCategoriesXml .= '<category>'.htmlspecialchars($cat, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</category>';
            }

            $items[] = <<<XML
        <item>
            <title>{$itemTitle}</title>
            <link>{$link}</link>
            <guid isPermaLink="false">{$link}</guid>
            <pubDate>{$pubDate}</pubDate>
            <description>{$itemDesc}</description>
            {$itemCategoriesXml}
        </item>
XML;
        }

        $channelItems = implode("\n", $items);

        $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>{$title}</title>
        <link>{$siteUrl}/blog</link>
        <description>{$description}</description>
        <lastBuildDate>{$lastBuildDate}</lastBuildDate>
        {$channelItems}
    </channel>
</rss>
XML;

        return response($rss, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}

