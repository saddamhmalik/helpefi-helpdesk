<?php

namespace App\Http\Middleware;

use App\Domains\Knowledge\Services\KnowledgeLocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePortalLocale
{
    public function __construct(private KnowledgeLocaleService $locales)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->locales->resolve($request);

        return $next($request);
    }
}
