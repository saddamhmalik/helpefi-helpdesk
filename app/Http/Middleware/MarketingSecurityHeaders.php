<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Support\CentralDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketingSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! CentralDomain::isCentralHost($request->getHost())) {
            return $response;
        }

        if ($request->is('admin', 'admin/*', 'razorpay/*', 'platform-notices/*')) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (! app()->environment('local') || $request->secure()) {
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSources = [
            "'self'",
            "'unsafe-inline'",
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
            'https://analytics.ahrefs.com',
        ];

        $styleSources = [
            "'self'",
            "'unsafe-inline'",
        ];

        $connectSources = [
            "'self'",
            'https://www.google-analytics.com',
            'https://www.googletagmanager.com',
            'https://analytics.ahrefs.com',
        ];

        if (app()->environment('local', 'testing')) {
            $scriptSources[] = 'blob:';

            foreach (['http://localhost:5173', 'http://127.0.0.1:5173', 'ws://localhost:5173', 'ws://127.0.0.1:5173'] as $devOrigin) {
                $scriptSources[] = $devOrigin;
                $styleSources[] = $devOrigin;
                $connectSources[] = $devOrigin;
            }
        }

        $frameSources = ["'self'"];

        if ($this->turnstileEnabled()) {
            $turnstileOrigin = 'https://challenges.cloudflare.com';
            $scriptSources[] = $turnstileOrigin;
            $connectSources[] = $turnstileOrigin;
            $frameSources[] = $turnstileOrigin;
        }

        $directives = [
            "default-src 'self'",
            'base-uri \'self\'',
            "form-action 'self'",
            "frame-ancestors 'self'",
            'frame-src '.implode(' ', $frameSources),
            'img-src \'self\' data: https: blob:',
            'font-src \'self\' data: https:',
            'object-src \'none\'',
            'script-src '.implode(' ', $scriptSources),
            'style-src '.implode(' ', $styleSources),
            'connect-src '.implode(' ', $connectSources),
        ];

        if (app()->environment('local', 'testing')) {
            $directives[] = 'script-src-elem '.implode(' ', $scriptSources);
            $directives[] = 'style-src-elem '.implode(' ', $styleSources);
        }

        return implode('; ', $directives);
    }

    private function turnstileEnabled(): bool
    {
        $secret = config('marketing_seo.turnstile.secret_key');

        return is_string($secret) && $secret !== '';
    }
}
