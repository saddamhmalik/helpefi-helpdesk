<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyWhenNotCentral
{
    public function __construct(
        private InitializeTenancyByDomain $initializeByDomain,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            return $next($request);
        }

        if (tenancy()->initialized) {
            return $next($request);
        }

        return $this->initializeByDomain->handle($request, $next);
    }
}
