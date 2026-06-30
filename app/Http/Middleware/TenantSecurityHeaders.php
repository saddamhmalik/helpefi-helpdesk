<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Support\CentralDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (CentralDomain::isCentralHost($request->getHost())) {
            return $response;
        }

        if ($request->is('api/*')) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());

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
        $scriptSources = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];
        $styleSources = ["'self'", "'unsafe-inline'"];
        $connectSources = ["'self'", 'https:', 'wss:'];
        $imgSources = ["'self'", 'data:', 'https:', 'blob:'];
        $fontSources = ["'self'", 'data:', 'https:'];
        $frameSources = ["'self'"];

        if (app()->environment('local', 'testing')) {
            $scriptSources[] = 'blob:';

            foreach (['http://localhost:5173', 'http://127.0.0.1:5173', 'ws://localhost:5173', 'ws://127.0.0.1:5173'] as $devOrigin) {
                $scriptSources[] = $devOrigin;
                $styleSources[] = $devOrigin;
                $connectSources[] = $devOrigin;
            }
        }

        if ($this->razorpayEnabled()) {
            foreach ($this->razorpayOrigins() as $origin) {
                $scriptSources[] = $origin;
                $frameSources[] = $origin;
                $connectSources[] = $origin;
            }
        }

        $wsUrl = config('broadcasting.connections.reverb.options.host')
            ?? config('realtime.ws_url');

        if (is_string($wsUrl) && $wsUrl !== '') {
            $parsed = parse_url($wsUrl);

            if (is_array($parsed) && ! empty($parsed['host'])) {
                $scheme = ($parsed['scheme'] ?? 'wss') === 'ws' ? 'ws' : 'wss';
                $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
                $connectSources[] = "{$scheme}://{$parsed['host']}{$port}";
            }
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            'frame-src '.implode(' ', $frameSources),
            'img-src '.implode(' ', $imgSources),
            'font-src '.implode(' ', $fontSources),
            "object-src 'none'",
            'script-src '.implode(' ', $scriptSources),
            'style-src '.implode(' ', $styleSources),
            'connect-src '.implode(' ', $connectSources),
        ]);
    }

    private function permissionsPolicy(): string
    {
        $payment = $this->razorpayEnabled() ? 'payment=(self)' : 'payment=()';

        return "camera=(), microphone=(), geolocation=(), {$payment}";
    }

    private function razorpayEnabled(): bool
    {
        return (bool) config('razorpay.enabled')
            && filled(config('razorpay.key'))
            && filled(config('razorpay.secret'));
    }

    /**
     * @return list<string>
     */
    private function razorpayOrigins(): array
    {
        return [
            'https://checkout.razorpay.com',
            'https://api.razorpay.com',
            'https://cdn.razorpay.com',
        ];
    }
}
