<?php

namespace App\Http\Middleware;

use App\Domains\Brands\Services\BrandService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAgent
{
    public function __construct(private BrandService $brands)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('customer')) {
            return redirect()->route('portal.my-tickets', ['brand' => $this->brands->defaultSlug()]);
        }

        if ($user->hasAnyRole(['admin', 'agent']) || $user->can('access.agent')) {
            return $next($request);
        }

        abort(403);
    }
}
