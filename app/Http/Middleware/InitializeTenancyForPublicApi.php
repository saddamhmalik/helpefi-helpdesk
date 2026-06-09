<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Models\TenantRouteMapping;
use App\Domains\Tenancy\Services\TenantRouteRegistryService;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyForPublicApi
{
    public function __construct(
        private TenantRouteRegistryService $registry,
        private InitializeTenancyByDomain $initializeByDomain,
    ) {
    }

    public function handle(Request $request, Closure $next, string $mode = 'widget'): Response
    {
        if (tenancy()->initialized) {
            return $next($request);
        }

        if ($this->isCentralDomain($request)) {
            return $this->initializeFromCentralRoute($request, $next, $mode);
        }

        return $this->initializeByDomain->handle($request, $next);
    }

    private function initializeFromCentralRoute(Request $request, Closure $next, string $mode): Response
    {
        $tenantId = $mode === 'inbound'
            ? $this->registry->resolveInboundTenant(
                $request->header('X-Channel-Token'),
                $request->input('to_email'),
            )
            : $this->registry->resolveTenantId(
                TenantRouteMapping::TYPE_WIDGET_KEY,
                (string) $request->header('X-Widget-Key', ''),
            );

        if (! $tenantId) {
            abort($mode === 'inbound' ? 422 : 404, $mode === 'inbound'
                ? 'No matching email inbox found.'
                : 'Invalid widget key.');
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }

    private function isCentralDomain(Request $request): bool
    {
        return in_array($request->getHost(), config('tenancy.central_domains'), true);
    }
}
