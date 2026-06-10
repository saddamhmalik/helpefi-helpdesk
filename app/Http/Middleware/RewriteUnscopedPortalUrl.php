<?php

namespace App\Http\Middleware;

use App\Domains\Brands\Models\Brand;
use App\Domains\Brands\Services\BrandService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RewriteUnscopedPortalUrl
{
    public function __construct(private BrandService $brands)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant('id')) {
            return $next($request);
        }

        $segments = $request->segments();

        if (($segments[0] ?? null) !== 'portal') {
            return $next($request);
        }

        $second = $segments[1] ?? null;

        if ($second === null || Brand::query()->where('slug', $second)->exists()) {
            return $next($request);
        }

        $path = '/portal/'.$this->brands->defaultSlug().'/'.implode('/', array_slice($segments, 1));
        $query = $request->getQueryString();
        $uri = $query ? "{$path}?{$query}" : $path;

        $status = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? 307 : 302;

        return redirect($uri, $status);
    }
}
