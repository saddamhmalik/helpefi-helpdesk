<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Support\CentralDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForgetTenantWebAuthOnCentral
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CentralDomain::isCentralHost($request->getHost()) || ! $request->hasSession()) {
            return $next($request);
        }

        $guard = Auth::guard('web');

        $request->session()->forget($guard->getName());
        $request->session()->forget($guard->getRecallerName());

        return $next($request);
    }
}
