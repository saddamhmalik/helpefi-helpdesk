<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ExpireLegacySessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $central = (string) config('tenancy.central_app_domain');

        if ($central === '') {
            return $response;
        }

        $legacyDomain = '.'.$central;

        foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $response->headers->setCookie(Cookie::create(
                $name,
                '',
                1,
                '/',
                $legacyDomain,
                true,
                false,
                false,
                Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }
}
