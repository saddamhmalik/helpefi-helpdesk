<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Support\CentralDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCentralWww
{
    public function handle(Request $request, Closure $next): Response
    {
        $central = CentralDomain::apex();
        $www = CentralDomain::www();

        if ($central !== '' && strtolower($request->getHost()) === $www) {
            $scheme = $request->getScheme();
            $uri = $request->getRequestUri();

            return redirect()->away("{$scheme}://{$central}{$uri}", 301);
        }

        return $next($request);
    }
}
