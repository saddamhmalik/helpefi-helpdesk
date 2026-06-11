<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Support\CentralDomain;
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
        if (CentralDomain::isCentralHost($request->getHost())) {
            return $next($request);
        }

        if (tenancy()->initialized) {
            return $next($request);
        }

        return $this->initializeByDomain->handle($request, $next);
    }
}
