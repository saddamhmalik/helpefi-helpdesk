<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            return $next($request);
        }

        if ($request->routeIs('tenant.blocked')) {
            return $next($request);
        }

        $tenant = tenant();

        if ($tenant && $tenant->is_blocked) {
            return redirect()->route('tenant.blocked');
        }

        return $next($request);
    }
}
