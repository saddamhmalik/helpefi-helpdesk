<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCentralWww
{
    public function handle(Request $request, Closure $next): Response
    {
        $central = (string) config('tenancy.central_app_domain');
        $www = 'www.'.$central;

        if ($central !== '' && strtolower($request->getHost()) === strtolower($www)) {
            $scheme = $request->getScheme();
            $uri = $request->getRequestUri();

            return redirect()->away("{$scheme}://{$central}{$uri}", 301);
        }

        return $next($request);
    }
}
