<?php

namespace App\Http\Middleware;

use App\Domains\Security\Services\SecuritySettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function __construct(private SecuritySettingService $security)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('two_factor_verified')) {
            auth()->logout();

            return redirect()->route('login');
        }

        if ($this->security->userMustEnrollMfa($user) && ! $this->isEnrollmentRoute($request)) {
            return redirect()->route('settings.profile')
                ->with('error', 'Two-factor authentication is required. Enable it on your profile.');
        }

        return $next($request);
    }

    private function isEnrollmentRoute(Request $request): bool
    {
        return $request->routeIs(
            'settings.profile',
            'settings.two-factor.*',
            'logout',
        );
    }
}
