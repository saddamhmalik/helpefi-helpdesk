<?php

namespace App\Http\Middleware;

use App\Domains\Platform\Services\PlatformAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformPermission
{
    public function __construct(private PlatformAuthorizationService $authorization)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::guard('platform')->user();

        if (! $user || ! $this->authorization->allows($user, $permission)) {
            abort(403);
        }

        return $next($request);
    }
}
