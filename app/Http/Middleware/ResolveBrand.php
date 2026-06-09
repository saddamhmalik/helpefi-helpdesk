<?php

namespace App\Http\Middleware;

use App\Domains\Brands\Models\Brand;
use App\Domains\Brands\Support\BrandContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBrand
{
    public function __construct(private BrandContext $brandContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $brand = $request->route('brand');

        if (! $brand instanceof Brand && is_string($brand) && $brand !== '') {
            $brand = Brand::query()
                ->where('slug', $brand)
                ->where('is_active', true)
                ->firstOrFail();
        }

        if ($brand instanceof Brand) {
            $this->brandContext->set($brand);
        }

        return $next($request);
    }
}
