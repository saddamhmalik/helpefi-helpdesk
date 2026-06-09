<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('customer')) {
            return redirect()->route('portal.my-tickets');
        }

        if ($user->hasAnyRole(['admin', 'agent']) || $user->can('access.agent')) {
            return $next($request);
        }

        abort(403);
    }
}
