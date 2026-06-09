<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('central.admin.login', 'central.admin.login.store')) {
            return $next($request);
        }

        $user = Auth::guard('platform')->user();

        if (! $user || ! $user->is_active) {
            if ($user && ! $user->is_active) {
                Auth::guard('platform')->logout();
            }

            return redirect()->route('central.admin.login');
        }

        return $next($request);
    }
}
