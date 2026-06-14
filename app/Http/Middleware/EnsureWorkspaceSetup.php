<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Services\TenantDummyDataService;
use App\Domains\Tenancy\Services\TenantSetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceSetup
{
    public function __construct(
        private TenantSetupService $setup,
        private TenantDummyDataService $dummyData,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! tenant('id') || ! $user?->hasRole('admin') || ! $this->setup->shouldRedirect() || $this->dummyData->isActive()) {
            return $next($request);
        }

        if ($this->allowsAccessDuringSetup($request)) {
            return $next($request);
        }

        return redirect()->route('setup');
    }

    private function allowsAccessDuringSetup(Request $request): bool
    {
        if ($request->routeIs('setup', 'setup.*', 'welcome', 'logout', 'two-factor.challenge', 'two-factor.verify', 'subscription.required', 'handbook.index')) {
            return true;
        }

        if ($request->routeIs('settings*') || $request->is('settings', 'settings/*', 'how-to')) {
            return true;
        }

        return false;
    }
}
