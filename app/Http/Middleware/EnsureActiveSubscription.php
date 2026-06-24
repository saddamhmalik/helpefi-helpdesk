<?php

namespace App\Http\Middleware;

use App\Domains\Billing\Repositories\SubscriptionRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(private SubscriptionRepository $subscriptions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant('id') || ! $request->user()) {
            return $next($request);
        }

        $subscription = $this->subscriptions->current();

        if ($subscription->isAccessible()) {
            return $next($request);
        }

        if ($this->allowsAccessWhenExpired($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'An active subscription is required.'], 402);
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
            'settings.billing.razorpay.verify',
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
