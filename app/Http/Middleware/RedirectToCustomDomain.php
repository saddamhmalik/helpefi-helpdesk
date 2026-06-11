<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Services\TenantDomainService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToCustomDomain
{
    public function __construct(private TenantDomainService $domains)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $tenant = tenant();

        if ($tenant && $this->domains->shouldRedirectToPrimary($tenant, $request->getHost())) {
            $target = $this->domains->redirectUrl($tenant, $request->getRequestUri());

            if ($target) {
                return redirect()->away($target, 301);
            }
        }

        return $next($request);
    }
}
