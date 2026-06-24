<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PortalRateLimiters
{
    public static function register(): void
    {
        RateLimiter::for('portal-ticket-submit', fn (Request $request) => self::limit($request, 10));

        RateLimiter::for('portal-track-lookup', fn (Request $request) => self::limit($request, 20));

        RateLimiter::for('portal-auth', fn (Request $request) => self::limit($request, 5));
    }

    private static function limit(Request $request, int $maxAttempts): Limit
    {
        if (app()->environment('local')) {
            return Limit::none();
        }

        return Limit::perMinute($maxAttempts)->by((string) $request->ip());
    }
}
