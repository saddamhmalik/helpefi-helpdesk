<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ClearLegacyCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('local')) {
            return $response;
        }

        $legacyDomain = '.helpdesk.test';
        $names = [
            config('session.cookie'),
            'XSRF-TOKEN',
            'laravel_session',
        ];

        foreach ($names as $name) {
            $response->headers->setCookie(Cookie::forget($name, '/', $legacyDomain));
        }

        return $response;
    }
}
