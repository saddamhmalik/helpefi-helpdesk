<?php

namespace App\Http\Middleware;

use App\Domains\Platform\Services\MarketingPageViewRecorder;
use App\Domains\Tenancy\Support\CentralDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackMarketingPageView
{
    public function __construct(private MarketingPageViewRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldTrack($request, $response)) {
            return;
        }

        $this->recorder->record($request);
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (! CentralDomain::isCentralHost($request->getHost())) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if ($request->is('admin', 'admin/*', 'api/*', 'razorpay/*', 'robots.txt', 'sitemap.xml')) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson() || $request->headers->has('X-Inertia')) {
            return false;
        }

        return str_contains((string) $response->headers->get('content-type'), 'text/html');
    }
}
