<?php

namespace App\Http\Middleware;

use App\Domains\Billing\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant('id') || ! $request->user()) {
            return $next($request);
        }

        $subscription = Subscription::query()->where('tenant_id', tenant('id'))->first();

        if (! $subscription || $subscription->isAccessible()) {
            return $next($request);
        }

        if ($this->allowsAccessWhenExpired($request)) {
            return $next($request);
        }

        return redirect()->route('subscription.required');
    }

    private function allowsAccessWhenExpired(Request $request): bool
    {
        if ($request->routeIs(
            'subscription.required',
            'settings.billing',
            'settings.billing.plan',
            'settings.billing.checkout',
            'settings.billing.portal',
            'logout',
        )) {
            return true;
        }

        if ($request->is('settings/billing', 'settings/billing/*')) {
            return true;
        }

        return false;
    }
}
