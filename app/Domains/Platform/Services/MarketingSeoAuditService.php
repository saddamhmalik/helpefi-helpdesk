<?php

namespace App\Domains\Platform\Services;

use App\Domains\Tenancy\Services\CentralSeoService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MarketingSeoAuditService
{
    private const CACHE_KEY = 'marketing:seo:audit:v1';

    private const LARGE_IMAGE_BYTES = 204_800;

    private const REDIRECT_TESTS = [
        ['/for/ecommerce', '/helpdesk-for-ecommerce'],
        ['/helpdesk-for-education', '/helpdesk-for-edtech'],
    ];

    public function __construct(private CentralSeoService $seo)
    {
    }

    public function run(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addHours(6), fn () => $this->performAudit());
    }

    public function summary(): ?array
    {
        $report = Cache::get(self::CACHE_KEY);

        if (! is_array($report) || ! isset($report['summary']) || ! is_array($report['summary'])) {
            return null;
        }

        return [
            'generated_at' => $report['generated_at'],
            'pages_scanned' => $report['pages_scanned'],
            'health_score' => $report['summary']['health_score'],
            'total_issues' => $report['summary']['total_issues'],
            'counts' => collect($report['summary'])
                ->except(['total_issues', 'health_score'])
                ->all(),
        ];
    }

    public function performAudit(): array
    {
        $siteUrl = $this->seo->siteUrl();
        $pages = [];
        $issues = [];
        $titles = [];
        $h1Texts = [];

        foreach ($this->seo->sitemapEntries() as $entry) {
            $url = (string) $entry['loc'];
            $path = $this->pathFromUrl($url, $siteUrl);
            $page = $this->auditPage($url, $path, $siteUrl);
            $pages[] = $page;

            foreach ($page['issues'] as $issue) {
                $issues[] = $issue;
            }

            if ($page['title'] !== '') {
                $titles[$page['title']][] = $url;
            }

            foreach ($page['h1_texts'] as $text) {
                if ($text !== '') {
                    $h1Texts[$text][] = $url;
                }
            }
        }

        foreach ($titles as $title => $urls) {
            if (count($urls) < 2) {
                continue;
            }

            $issues[] = $this->issue('duplicate_title', 'warning', $urls[0], 'Title is duplicated on '.count($urls).' pages.', [
                'title' => $title,
                'urls' => $urls,
            ]);
        }

        foreach ($h1Texts as $text => $urls) {
            if (count($urls) < 2) {
                continue;
            }

            $issues[] = $this->issue('duplicate_h1', 'warning', $urls[0], 'H1 is duplicated on '.count($urls).' pages.', [
                'h1' => $text,
                'urls' => $urls,
            ]);
        }

        foreach ($this->auditRedirectChains($siteUrl) as $redirectIssue) {
            $issues[] = $redirectIssue;
        }

        foreach ($this->auditInternalLinks($pages, $siteUrl) as $linkIssue) {
            $issues[] = $linkIssue;
        }

        foreach ($this->auditOrphanPages($pages, $siteUrl) as $orphanIssue) {
            $issues[] = $orphanIssue;
        }

        $summary = $this->buildSummary($issues, count($pages));

        return [
            'generated_at' => now()->toIso8601String(),
            'site_url' => $siteUrl,
            'pages_scanned' => count($pages),
            'summary' => $summary,
            'issues' => $issues,
            'pages' => collect($pages)
                ->map(fn (array $page) => collect($page)->except(['html', 'h1_texts'])->all())
                ->values()
                ->all(),
        ];
    }

    private function auditPage(string $url, string $path, string $siteUrl): array
    {
        $response = $this->fetch($path);
        $html = (string) $response['body'];
        $xpath = $this->xpath($html);
        $issues = [];

        $title = $this->textContent($xpath, '//title');
        $description = $this->metaContent($xpath, 'description');
        $canonical = $this->linkHref($xpath, 'canonical');
        $h1Nodes = $xpath->query('//h1');
        $h1Count = $h1Nodes ? $h1Nodes->length : 0;
        $h1Texts = [];

        if ($h1Nodes) {
            foreach ($h1Nodes as $node) {
                $h1Texts[] = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
            }
        }


        if ($h1Count === 0) {
            $issues[] = $this->issue('missing_h1', 'error', $url, 'No H1 found in rendered HTML.');
        } elseif ($h1Count > 1) {
            $issues[] = $this->issue('duplicate_h1', 'error', $url, 'Multiple H1 elements detected.', [
                'html_h1_count' => $h1Count,
            ]);
        }

        if ($title === '') {
            $issues[] = $this->issue('missing_title', 'error', $url, 'Missing document title.');
        }

        if ($description === '') {
            $issues[] = $this->issue('missing_description', 'error', $url, 'Missing meta description.');
        } else {
            $descriptionLength = mb_strlen($description);

            if ($descriptionLength > 160) {
                $issues[] = $this->issue('description_too_long', 'warning', $url, 'Meta description exceeds 160 characters.', [
                    'length' => $descriptionLength,
                ]);
            } elseif ($descriptionLength < 120) {
                $issues[] = $this->issue('description_too_short', 'warning', $url, 'Meta description is shorter than 120 characters.', [
                    'length' => $descriptionLength,
                ]);
            }
        }

        if ($title !== '') {
            $titleLength = mb_strlen($title);

            if ($titleLength < 30) {
                $issues[] = $this->issue('title_too_short', 'warning', $url, 'Document title is shorter than 30 characters.', [
                    'length' => $titleLength,
                ]);
            }
        }

        $wordCount = str_word_count(strip_tags($html));

        if ($wordCount < 200) {
            $issues[] = $this->issue('low_word_count', 'warning', $url, 'Page has low word count in initial HTML.', [
                'words' => $wordCount,
            ]);
        }

        $outgoingLinks = $this->extractLinks($xpath, $url, $siteUrl);

        if ($outgoingLinks === []) {
            $issues[] = $this->issue('no_outgoing_links', 'error', $url, 'Page has no outgoing links in initial HTML.');
        }

        if ($canonical === '') {
            $issues[] = $this->issue('canonical_errors', 'error', $url, 'Missing canonical link.');
        } else {
            $canonicalIssues = $this->canonicalIssues($url, $canonical, $siteUrl);
            foreach ($canonicalIssues as $message) {
                $issues[] = $this->issue('canonical_errors', 'error', $url, $message, ['canonical' => $canonical]);
            }
        }

        if (! $this->hasJsonLd($xpath)) {
            $issues[] = $this->issue('missing_schema', 'warning', $url, 'No JSON-LD schema found.');
        }

        foreach ($this->imagesMissingAlt($xpath) as $src) {
            $issues[] = $this->issue('missing_alt', 'warning', $url, 'Image missing alt text.', ['src' => $src]);
        }

        foreach ($this->largeImages($xpath, $canonical !== '' ? $canonical : $url, $siteUrl) as $image) {
            $issues[] = $this->issue('large_images', 'warning', $url, 'Large image detected.', $image);
        }

        return [
            'url' => $url,
            'path' => $path,
            'status' => $response['status'],
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'h1_count' => $h1Count,
            'h1_texts' => $h1Texts,
            'schema' => $this->hasJsonLd($xpath),
            'issues' => $issues,
            'links' => $outgoingLinks,
            'html' => $html,
        ];
    }

    private function auditRedirectChains(string $siteUrl): array
    {
        $issues = [];

        foreach (self::REDIRECT_TESTS as [$from, $expectedTarget]) {
            $chain = $this->followRedirects($from);
            $finalPath = $this->pathFromUrl($chain['final_url'], $siteUrl);

            if ($chain['hops'] > 1) {
                $issues[] = $this->issue(
                    'redirect_chains',
                    'warning',
                    $siteUrl.$from,
                    'Redirect chain has '.$chain['hops'].' hops.',
                    ['chain' => $chain['chain']],
                );
            }

            if ($finalPath !== $expectedTarget) {
                $issues[] = $this->issue(
                    'redirect_chains',
                    'error',
                    $siteUrl.$from,
                    'Redirect does not land on expected destination.',
                    ['expected' => $expectedTarget, 'actual' => $finalPath, 'chain' => $chain['chain']],
                );
            }
        }

        return $issues;
    }

    private function auditInternalLinks(array $pages, string $siteUrl): array
    {
        $issues = [];
        $checked = [];
        $hosts = [$this->hostFromUrl($siteUrl), config('tenancy.central_app_domain')];

        $links = collect($pages)
            ->flatMap(fn (array $page) => $page['links'] ?? [])
            ->unique()
            ->values();

        foreach ($links as $link) {
            if (isset($checked[$link])) {
                continue;
            }

            $checked[$link] = true;
            $path = $this->internalPath($link, $siteUrl, $hosts);

            if ($path === null) {
                continue;
            }

            $response = $this->fetch($path, followRedirects: true);

            if ($response['status'] >= 400) {
                $issues[] = $this->issue('broken_links', 'error', $link, 'Link returns HTTP '.$response['status'].'.');
                continue;
            }

            if (count($response['chain']) > 2) {
                $issues[] = $this->issue('redirect_chains', 'warning', $link, 'Internal link passes through a redirect chain.', [
                    'chain' => $response['chain'],
                ]);
            }
        }

        return $issues;
    }

    private function auditOrphanPages(array $pages, string $siteUrl): array
    {
        $issues = [];
        $hosts = [$this->hostFromUrl($siteUrl), config('tenancy.central_app_domain')];
        $incoming = [];

        foreach ($pages as $page) {
            foreach ($page['links'] ?? [] as $link) {
                $path = $this->internalPath($link, $siteUrl, $hosts);

                if ($path !== null) {
                    $incoming[$path] = ($incoming[$path] ?? 0) + 1;
                }
            }
        }

        foreach ($pages as $page) {
            $path = (string) ($page['path'] ?? '/');

            if ($path === '/') {
                continue;
            }

            if (($incoming[$path] ?? 0) === 0) {
                $issues[] = $this->issue('orphan_page', 'warning', $page['url'], 'No incoming internal links found in initial HTML.');
            }
        }

        return $issues;
    }

    private function fetch(string $path, bool $followRedirects = false): array
    {
        $host = (string) config('tenancy.central_app_domain');
        $chain = [];
        $current = $path;
        $status = 200;
        $body = '';
        $maxHops = $followRedirects ? 5 : 1;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $request = Request::create($current, 'GET', [], [], [], [
                'HTTP_HOST' => $host,
                'HTTPS' => 'on',
            ]);

            $response = app()->handle($request);
            $status = $response->getStatusCode();
            $chain[] = ['path' => $current, 'status' => $status];

            if (! in_array($status, [301, 302, 303, 307, 308], true) || ! $followRedirects) {
                $body = $response->getContent() ?: '';
                break;
            }

            $location = $response->headers->get('Location');

            if (! is_string($location) || $location === '') {
                $body = $response->getContent() ?: '';
                break;
            }

            $current = str_starts_with($location, 'http')
                ? parse_url($location, PHP_URL_PATH) ?: '/'
                : $location;
        }

        return [
            'status' => $status,
            'body' => $body,
            'chain' => $chain,
            'final_url' => $this->seo->siteUrl().($current === '' ? '/' : $current),
        ];
    }

    private function followRedirects(string $path): array
    {
        $host = (string) config('tenancy.central_app_domain');
        $chain = [];
        $current = $path;
        $status = 200;

        for ($hop = 0; $hop < 6; $hop++) {
            $request = Request::create($current, 'GET', [], [], [], [
                'HTTP_HOST' => $host,
                'HTTPS' => 'on',
            ]);

            $response = app()->handle($request);
            $status = $response->getStatusCode();
            $chain[] = ['path' => $current, 'status' => $status];

            if (! in_array($status, [301, 302, 303, 307, 308], true)) {
                break;
            }

            $location = $response->headers->get('Location');

            if (! is_string($location) || $location === '') {
                break;
            }

            $current = str_starts_with($location, 'http')
                ? parse_url($location, PHP_URL_PATH) ?: '/'
                : $location;
        }

        return [
            'hops' => max(0, count($chain) - 1),
            'chain' => $chain,
            'final_url' => $this->seo->siteUrl().($current === '' ? '/' : $current),
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);

        return new DOMXPath($dom);
    }

    private function textContent(DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)?->item(0);

        return trim($node?->textContent ?? '');
    }

    private function metaContent(DOMXPath $xpath, string $name): string
    {
        $node = $xpath->query('//meta[@name="'.$name.'"]')?->item(0);

        return trim((string) $node?->getAttribute('content'));
    }

    private function linkHref(DOMXPath $xpath, string $rel): string
    {
        $node = $xpath->query('//link[@rel="'.$rel.'"]')?->item(0);

        return trim((string) $node?->getAttribute('href'));
    }

    private function hasJsonLd(DOMXPath $xpath): bool
    {
        $nodes = $xpath->query('//script[@type="application/ld+json"]');

        return $nodes !== false && $nodes->length > 0;
    }

    private function imagesMissingAlt(DOMXPath $xpath): array
    {
        $missing = [];
        $nodes = $xpath->query('//img');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $alt = trim((string) $node->getAttribute('alt'));
            $src = trim((string) $node->getAttribute('src'));

            if ($alt !== '' || $src === '') {
                continue;
            }

            $missing[] = $src;
        }

        return $missing;
    }

    private function largeImages(DOMXPath $xpath, string $pageUrl, string $siteUrl): array
    {
        $large = [];
        $sources = [];

        foreach ($xpath->query('//meta[@property="og:image"] | //meta[@name="twitter:image"] | //img') ?: [] as $node) {
            $src = trim((string) ($node->getAttribute('content') ?: $node->getAttribute('src')));

            if ($src !== '') {
                $sources[] = $src;
            }
        }

        foreach (array_unique($sources) as $src) {
            $bytes = $this->imageBytes($src, $siteUrl);

            if ($bytes !== null && $bytes >= self::LARGE_IMAGE_BYTES) {
                $large[] = [
                    'src' => $src,
                    'bytes' => $bytes,
                    'kilobytes' => (int) round($bytes / 1024),
                ];
            }
        }

        return $large;
    }

    private function imageBytes(string $src, string $siteUrl): ?int
    {
        if (str_starts_with($src, 'data:')) {
            return null;
        }

        $path = parse_url($src, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = $src;
        }

        $local = public_path(ltrim($path, '/'));

        if (is_file($local)) {
            return (int) filesize($local);
        }

        $absolute = str_starts_with($src, 'http') ? $src : rtrim($siteUrl, '/').'/'.ltrim($src, '/');
        $headers = @get_headers($absolute, true);

        if (! is_array($headers)) {
            return null;
        }

        $length = $headers['Content-Length'] ?? $headers['content-length'] ?? null;

        return is_numeric($length) ? (int) $length : null;
    }

    private function extractLinks(DOMXPath $xpath, string $pageUrl, string $siteUrl): array
    {
        $links = [];
        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $href = trim((string) $node->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $links[] = $this->absoluteUrl($href, $pageUrl, $siteUrl);
        }

        return array_values(array_unique($links));
    }

    private function canonicalIssues(string $url, string $canonical, string $siteUrl): array
    {
        $issues = [];
        $normalizedCanonical = rtrim($canonical, '/');
        $normalizedUrl = rtrim($url, '/');

        if (! str_starts_with($canonical, 'http')) {
            $issues[] = 'Canonical URL is not absolute.';
        }

        if ($normalizedCanonical !== $normalizedUrl) {
            $issues[] = 'Canonical URL does not match page URL.';
        }

        $configuredHost = parse_url($siteUrl, PHP_URL_HOST);
        $canonicalHost = parse_url($canonical, PHP_URL_HOST);

        if (is_string($configuredHost) && is_string($canonicalHost) && $configuredHost !== $canonicalHost) {
            $issues[] = 'Canonical host does not match configured marketing site URL.';
        }

        return $issues;
    }

    private function buildSummary(array $issues, int $pageCount): array
    {
        $counts = [
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
        ];

        foreach ($issues as $issue) {
            $type = $issue['type'];

            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        $totalIssues = array_sum($counts);
        $penalty = min(100, ($counts['missing_h1'] * 8)
            + ($counts['duplicate_h1'] * 5)
            + ($counts['missing_title'] * 8)
            + ($counts['duplicate_title'] * 4)
            + ($counts['missing_description'] * 6)
            + ($counts['canonical_errors'] * 6)
            + ($counts['broken_links'] * 10)
            + ($counts['redirect_chains'] * 3)
            + ($counts['missing_alt'] * 2)
            + ($counts['large_images'] * 2)
            + ($counts['missing_schema'] * 4));

        return [
            ...$counts,
            'total_issues' => $totalIssues,
            'health_score' => max(0, 100 - $penalty),
            'pages_scanned' => $pageCount,
        ];
    }

    private function issue(string $type, string $severity, string $url, string $message, array $details = []): array
    {
        return [
            'type' => $type,
            'severity' => $severity,
            'url' => $url,
            'message' => $message,
            'details' => $details,
        ];
    }

    private function pathFromUrl(string $url, string $siteUrl): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private function hostFromUrl(string $url): string
    {
        return (string) parse_url($url, PHP_URL_HOST);
    }

    private function absoluteUrl(string $href, string $pageUrl, string $siteUrl): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }

        $base = rtrim($pageUrl, '/');

        if (str_starts_with($href, '/')) {
            return rtrim($siteUrl, '/').$href;
        }

        return $base.'/'.ltrim($href, '/');
    }

    private function internalPath(string $url, string $siteUrl, array $hosts): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        if ($host === null) {
            return $path;
        }

        if (! in_array($host, array_filter($hosts), true)) {
            return null;
        }

        if (Str::startsWith($path, ['/admin', '/api', '/storage', '/dashboard'])) {
            return null;
        }

        return $path;
    }
}
