<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\MarketingPageView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingPageViewRecorder
{
    private const BOT_SIGNATURE = '/(bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|quora link preview|pingdom|monitor|preview|fetch|curl|wget|python-requests|headless|lighthouse|gtmetrix|uptime)/i';

    public function record(Request $request): void
    {
        try {
            MarketingPageView::query()->create([
                'path' => Str::limit($request->getPathInfo() ?: '/', 2048, ''),
                'referrer_host' => $this->referrerHost($request),
                'visitor_hash' => $this->visitorHash($request),
                'is_bot' => $this->isBot((string) $request->userAgent()),
                'visited_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }

        return (bool) preg_match(self::BOT_SIGNATURE, $userAgent);
    }

    private function visitorHash(Request $request): string
    {
        $salt = (string) config('app.key');

        return hash('sha256', $request->ip().'|'.((string) $request->userAgent()).'|'.$salt);
    }

    private function referrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) ? Str::limit($host, 255, '') : null;
    }
}
